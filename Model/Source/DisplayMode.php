<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DisplayMode implements OptionSourceInterface
{
    public const OFF = 'off';
    public const DIRECT = 'direct';
    public const NESTED = 'nested';
    public const DIALOG_ONLY = 'dialog_only';
    public const MODES = [self::OFF, self::DIRECT, self::NESTED, self::DIALOG_ONLY];

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::OFF, 'label' => __('Off')],
            ['value' => self::DIRECT, 'label' => __('Direct (complete graphic)')],
            ['value' => self::NESTED, 'label' => __('Nested (button opens dialog)')],
            ['value' => self::DIALOG_ONLY, 'label' => __('Dialog only (place your own trigger)')],
        ];
    }
}
