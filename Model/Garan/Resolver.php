<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model\Garan;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use InvalidArgumentException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Psr\Log\LoggerInterface;

class Resolver implements GaranLabelResolverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly LabelValidator $labelValidator,
        private readonly DurationParser $durationParser,
        private readonly ProductResource $productResource,
        private readonly GaranLabelDataFactory $garanLabelDataFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function forProduct(ProductInterface $product, ?int $storeId = null): ?GaranLabelDataInterface
    {
        $typeId = (string) $product->getTypeId();
        $storeId ??= $product instanceof Product ? (int) $product->getStoreId() : 0;
        if ($typeId !== ProductType::TYPE_SIMPLE || $this->isExcludedType($typeId, $storeId)) {
            return null;
        }

        $values = $this->getAttributeValues($product, $storeId);
        $violations = $this->labelValidator->getViolations(
            $values[Attributes::BRAND],
            $values[Attributes::MODEL_IDENTIFIER],
            $values[Attributes::DURATION_YEARS],
            $values[Attributes::TERMS_URL]
        );
        if ($violations !== []) {
            return null;
        }

        return $this->createLabel(
            (int) $product->getId(),
            (string) $product->getName(),
            (string) $product->getSku(),
            $this->labelValidator->normalizeText($values[Attributes::BRAND]),
            $this->labelValidator->normalizeText($values[Attributes::MODEL_IDENTIFIER]),
            (float) $this->durationParser->parse($values[Attributes::DURATION_YEARS]),
            $this->labelValidator->normalizeText($values[Attributes::TERMS_URL])
        );
    }

    public function forQuoteItem(AbstractItem $item): array
    {
        $storeId = $this->getQuoteItemStoreId($item);
        $product = $item->getProduct();
        if (!$product instanceof ProductInterface || $this->isExcludedType((string) $product->getTypeId(), $storeId)) {
            return [];
        }

        $children = $item->getChildren();
        $sources = count($children) > 0 ? $children : [$item];
        $labels = [];
        foreach ($sources as $source) {
            $sourceProduct = $source->getProduct();
            $label = $sourceProduct instanceof ProductInterface ? $this->forProduct($sourceProduct, $storeId) : null;
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function forOrderItem(OrderItem $item): array
    {
        $snapshot = $item->getData(Attributes::ORDER_ITEM_SNAPSHOT_COLUMN);
        if (!is_string($snapshot) || trim($snapshot) === '') {
            return [];
        }

        try {
            $entries = $this->json->unserialize($snapshot);
        } catch (InvalidArgumentException $exception) {
            $this->logMalformedSnapshot($item, $exception->getMessage());
            return [];
        }
        if (!is_array($entries)) {
            $this->logMalformedSnapshot($item, 'snapshot is not a list');
            return [];
        }

        $labels = [];
        foreach ($entries as $entry) {
            $label = is_array($entry) ? $this->fromSnapshotEntry($entry) : null;
            if ($label === null) {
                $this->logMalformedSnapshot($item, 'invalid entry skipped');
                continue;
            }
            $labels[] = $label;
        }

        return $labels;
    }

    /**
     * @param array<mixed> $entry
     */
    private function fromSnapshotEntry(array $entry): ?GaranLabelDataInterface
    {
        $productId = $entry[GaranLabelDataInterface::PRODUCT_ID] ?? null;
        $productName = $entry[GaranLabelDataInterface::PRODUCT_NAME] ?? null;
        $sku = $entry[GaranLabelDataInterface::SKU] ?? null;
        $brand = $entry[GaranLabelDataInterface::BRAND] ?? null;
        $model = $entry[GaranLabelDataInterface::MODEL_IDENTIFIER] ?? null;
        $termsUrl = $entry[GaranLabelDataInterface::TERMS_URL] ?? null;
        $duration = $this->durationParser->parse($entry[GaranLabelDataInterface::DURATION_YEARS] ?? null);

        if (!is_int($productId) && !(is_string($productId) && ctype_digit($productId))) {
            return null;
        }
        if (!is_string($productName) || !is_string($sku) || !is_string($brand) || !is_string($model)
            || !is_string($termsUrl) || $brand === '' || $model === '' || $termsUrl === '' || $duration === null
        ) {
            return null;
        }

        return $this->createLabel((int) $productId, $productName, $sku, $brand, $model, $duration, $termsUrl);
    }

    private function createLabel(
        int $productId,
        string $productName,
        string $sku,
        string $brand,
        string $modelIdentifier,
        float $durationYears,
        string $termsUrl
    ): GaranLabelDataInterface {
        return $this->garanLabelDataFactory->create([
            'productId' => $productId,
            'productName' => $productName,
            'sku' => $sku,
            'brand' => $brand,
            'modelIdentifier' => $modelIdentifier,
            'durationYears' => $durationYears,
            'termsUrl' => $termsUrl,
        ]);
    }

    /**
     * Label attribute values of the product; codes not present on the product at all are read from the database.
     *
     * A code present with value null counts as loaded and empty: callers iterating a collection that selected the
     * label attributes set the missing codes to null, so no query per product is issued.
     *
     * @return array<string, mixed>
     */
    private function getAttributeValues(ProductInterface $product, int $storeId): array
    {
        $values = [];
        $missing = [];
        foreach (Attributes::ALL as $code) {
            $values[$code] = null;
            if ($product instanceof DataObject && $product->hasData($code)) {
                $values[$code] = $product->getData($code);
            } elseif (!$product instanceof DataObject && $product->getCustomAttribute($code) !== null) {
                $values[$code] = $product->getCustomAttribute($code)->getValue();
            } else {
                $missing[] = $code;
            }
        }

        $productId = (int) $product->getId();
        if ($missing === [] || $productId === 0) {
            return $values;
        }

        return array_merge($values, $this->loadRawValues($productId, $missing, $storeId));
    }

    /**
     * @param list<string> $codes
     * @return array<string, mixed>
     */
    private function loadRawValues(int $productId, array $codes, int $storeId): array
    {
        $raw = $this->productResource->getAttributeRawValue($productId, $codes, $storeId);
        if (is_array($raw)) {
            return array_intersect_key($raw, array_flip($codes));
        }

        // A scalar result means exactly one value exists, and its code is only known when one code was requested.
        // With several requested codes at least one required field is empty, so the label is incomplete anyway.
        return count($codes) === 1 && $raw !== false ? [$codes[0] => $raw] : [];
    }

    private function isExcludedType(string $typeId, int $storeId): bool
    {
        return in_array($typeId, $this->config->getExcludedProductTypes($storeId), true);
    }

    private function getQuoteItemStoreId(AbstractItem $item): int
    {
        $quote = $item->getQuote();
        if ($quote !== null && $quote->getStoreId() !== null) {
            return (int) $quote->getStoreId();
        }

        return (int) $item->getData('store_id');
    }

    private function logMalformedSnapshot(OrderItem $item, string $reason): void
    {
        $this->logger->debug(
            sprintf('CopeX_WarrantyLabel: malformed GARAN snapshot on order item %s: %s', $item->getId(), $reason)
        );
    }
}
