<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\ViewModel;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;

/**
 * Texts and image URLs of one EU GARAN label shared by storefront, checkout and email output.
 */
class GaranLabelPresenter
{
    public function __construct(
        private readonly GaranPngRenderer $pngRenderer
    ) {
    }

    /**
     * Accessible text carrying the complete label information, e.g. for alt attributes and dialog names.
     *
     * @param GaranLabelDataInterface $label
     * @return string
     */
    public function getAccessibleLabel(GaranLabelDataInterface $label): string
    {
        return (string) __(
            'GARAN – producer guarantee %1 years, %2 %3',
            $label->getFormattedDuration(),
            $label->getBrand(),
            $label->getModelIdentifier()
        );
    }

    /**
     * Plain label values for JSON consumers.
     *
     * @param GaranLabelDataInterface $label
     * @return array{productName: string, brand: string, model: string, years: string, termsUrl: string, alt: string}
     */
    public function toArray(GaranLabelDataInterface $label): array
    {
        return [
            'productName' => $label->getProductName(),
            'brand' => $label->getBrand(),
            'model' => $label->getModelIdentifier(),
            'years' => $label->getFormattedDuration(),
            'termsUrl' => $label->getTermsUrl(),
            'alt' => $this->getAccessibleLabel($label),
        ];
    }

    /**
     * Label values plus the cached PNG URLs; '' when an image is unavailable (text fallback) or not requested.
     *
     * @param GaranLabelDataInterface $label
     * @param bool $withNested
     * @param int|null $storeId
     * @return array<string, string>
     */
    public function toViewData(GaranLabelDataInterface $label, bool $withNested, ?int $storeId = null): array
    {
        $data = $this->toArray($label);
        $data['pngFull'] = (string) $this->pngRenderer->getUrl($label, GaranPngRenderer::VARIANT_FULL, $storeId);
        $data['pngNested'] = $withNested
            ? (string) $this->pngRenderer->getUrl($label, GaranPngRenderer::VARIANT_NESTED, $storeId)
            : '';

        return $data;
    }
}
