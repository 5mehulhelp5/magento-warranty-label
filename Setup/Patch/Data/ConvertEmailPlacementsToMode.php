<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Setup\Patch\Data;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\Model\Source\EmailMode;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The two email fields carried a storefront display mode up to 1.2.0 and now carry an EmailMode.
 *
 * Without this the admin renders a stored "direct" as the first option and switches the graphic off the next time
 * someone saves the section. The intermediate yes/no shape from an unreleased 1.3.0 build is converted as well.
 */
class ConvertEmailPlacementsToMode implements DataPatchInterface
{
    private const PATHS = [
        Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
        Config::XML_PATH_GARAN_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
    ];

    /**
     * Every value that used to mean "the graphic is in the email", in both earlier shapes.
     */
    private const SHOWN_VALUES = [DisplayMode::DIRECT, DisplayMode::NESTED, DisplayMode::DIALOG_ONLY, '1'];
    private const HIDDEN_VALUES = [DisplayMode::OFF, '0'];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $connection->update(
            $table,
            ['value' => EmailMode::INLINE],
            ['path IN (?)' => self::PATHS, 'value IN (?)' => self::SHOWN_VALUES]
        );
        $connection->update(
            $table,
            ['value' => EmailMode::NO],
            ['path IN (?)' => self::PATHS, 'value IN (?)' => self::HIDDEN_VALUES]
        );

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
