<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\ViewModel;

use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * EU GARAN label on the product page, shown as cached PNG images.
 *
 * Simple product: the label. Configurable product: a JSON map of the qualifying variants whose image URLs,
 * alt text and terms link garan-variant.js swaps on variant selection. Any failure degrades to "no label".
 */
class GaranProduct implements ArgumentInterface
{
    public const DIALOG_ID = 'copex-wl-garan-pdp';
    private const CHILD_ATTRIBUTES = ['name', 'sku'];

    public function __construct(
        private readonly Config $config,
        private readonly GaranLabelResolverInterface $resolver,
        private readonly GaranLabelPresenter $presenter,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Display mode of the label on the product page of the current store.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->config->getGaranMode(Config::PLACEMENT_PDP);
    }

    /**
     * Nested mode: button with the nested label opens a dialog with the full label.
     *
     * @return bool
     */
    /**
     * True for both dialog modes; "dialog_only" differs from "nested" only in who supplies the trigger.
     */
    public function isNested(): bool
    {
        return in_array($this->getMode(), [DisplayMode::NESTED, DisplayMode::DIALOG_ONLY], true);
    }

    /**
     * False in "dialog_only": the shop places its own element with class copex-wl-trigger and aria-controls.
     */
    public function hasTrigger(): bool
    {
        return $this->getMode() === DisplayMode::NESTED;
    }

    /**
     * Id of the dialog with the full label.
     *
     * @return string
     */
    public function getDialogId(): string
    {
        return self::DIALOG_ID;
    }

    /**
     * Link target of the QR code on the label.
     *
     * @return string
     */
    public function getInfoUrl(): string
    {
        return LanguageRegistry::GARAN_INFO_URL;
    }

    /**
     * Label of a qualifying simple product: label values plus pngFull and pngNested ('' = text fallback / direct).
     *
     * @param ProductInterface $product
     * @return array<string, string>|null
     */
    public function getLabel(ProductInterface $product): ?array
    {
        $mode = $this->getMode();
        if ($mode === DisplayMode::OFF) {
            return null;
        }

        try {
            $storeId = $this->getStoreId($product);
            $label = $this->resolver->forProduct($product, $storeId);

            return $label === null
                ? null
                : $this->presenter->toViewData($label, $mode === DisplayMode::NESTED, $storeId);
        } catch (Throwable $exception) {
            $this->logFailure($product, $exception);

            return null;
        }
    }

    /**
     * Qualifying variants of a configurable product, or null.
     *
     * Children: childId => {attributes: {attributeId: optionId}, label: {alt, pngFull, pngNested, termsUrl}}.
     *
     * @param ProductInterface $product
     * @return array{children: array<int|string, array<string, mixed>>}|null
     */
    public function getVariants(ProductInterface $product): ?array
    {
        $mode = $this->getMode();
        if ($mode === DisplayMode::OFF || !$product instanceof Product) {
            return null;
        }

        try {
            $typeInstance = $product->getTypeInstance();
            if (!$typeInstance instanceof Configurable) {
                return null;
            }
            $children = $this->getQualifyingChildren($product, $typeInstance, $mode === DisplayMode::NESTED);

            return $children === [] ? null : ['children' => $children];
        } catch (Throwable $exception) {
            $this->logFailure($product, $exception);

            return null;
        }
    }

    /**
     * Value of data-mage-init: variant switching for configurables, otherwise only the dialog.
     *
     * @param array<string, mixed>|null $variants
     * @return string
     */
    public function getMageInit(?array $variants): string
    {
        if ($variants === null) {
            return (string) $this->json->serialize(['CopeX_WarrantyLabel/js/dialog' => new stdClass()]);
        }

        return (string) $this->json->serialize([
            'CopeX_WarrantyLabel/js/garan-variant' => ['children' => $variants['children']],
        ]);
    }

    /**
     * Children with a complete option selection and a valid label, loaded with one collection query.
     *
     * @param Product $product
     * @param Configurable $typeInstance
     * @param bool $withNested
     * @return array<int|string, array<string, mixed>>
     */
    private function getQualifyingChildren(Product $product, Configurable $typeInstance, bool $withNested): array
    {
        $attributeCodes = [];
        foreach ($typeInstance->getConfigurableAttributes($product) as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            if ($productAttribute !== null) {
                $attributeCodes[(string) $productAttribute->getId()] = (string) $productAttribute->getAttributeCode();
            }
        }
        if ($attributeCodes === []) {
            return [];
        }

        $collection = $typeInstance->getUsedProductCollection($product);
        $collection->addAttributeToSelect(
            array_merge(Attributes::ALL, self::CHILD_ATTRIBUTES, array_values($attributeCodes))
        );
        // The duration is required and global: children without it can never have a label.
        $collection->addAttributeToFilter(Attributes::DURATION_YEARS, ['notnull' => true]);

        $storeId = (int) $product->getStoreId();
        $children = [];
        foreach ($collection as $child) {
            $selection = $this->getSelection($child, $attributeCodes);
            if ($selection === null) {
                continue;
            }
            $this->fillMissingLabelAttributes($child);
            $label = $this->resolver->forProduct($child, $storeId);
            if ($label === null) {
                continue;
            }
            $data = $this->presenter->toViewData($label, $withNested, $storeId);
            $children[(string) $child->getId()] = [
                'attributes' => $selection,
                'label' => [
                    'alt' => $data['alt'],
                    'pngFull' => $data['pngFull'],
                    'pngNested' => $data['pngNested'],
                    'termsUrl' => $data['termsUrl'],
                ],
            ];
        }

        return $children;
    }

    /**
     * Option id per configurable attribute id, or null when the child lacks a value.
     *
     * @param Product $child
     * @param array<int|string, string> $attributeCodes
     * @return array<int|string, string>|null
     */
    private function getSelection(Product $child, array $attributeCodes): ?array
    {
        $selection = [];
        foreach ($attributeCodes as $attributeId => $code) {
            $value = $child->getData($code);
            if ($value === null || $value === '' || is_array($value)) {
                return null;
            }
            $selection[(string) $attributeId] = (string) $value;
        }

        return $selection;
    }

    /**
     * EAV collections only set attributes that have a value. The collection selected all label attributes, so a
     * missing one is empty: set it to null so the resolver does not load it from the database per child.
     *
     * @param Product $child
     * @return void
     */
    private function fillMissingLabelAttributes(Product $child): void
    {
        foreach (Attributes::ALL as $code) {
            if (!$child->hasData($code)) {
                $child->setData($code, null);
            }
        }
    }

    private function getStoreId(ProductInterface $product): ?int
    {
        return $product instanceof Product ? (int) $product->getStoreId() : null;
    }

    private function logFailure(ProductInterface $product, Throwable $exception): void
    {
        $this->logger->error('CopeX_WarrantyLabel: GARAN product label could not be rendered.', [
            'product_id' => $product->getId(),
            'exception' => $exception,
        ]);
    }
}
