<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Setup\Patch\Data;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The two email fields changed from a display mode to yes/no. Without this the admin renders a stored "direct" as
 * "No" and switches the graphic off the next time someone saves the section.
 */
class ConvertEmailPlacementsToFlag implements DataPatchInterface
{
    private const PATHS = [
        Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
        Config::XML_PATH_GARAN_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
    ];

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
            ['value' => '1'],
            [
                'path IN (?)' => self::PATHS,
                'value IN (?)' => [DisplayMode::DIRECT, DisplayMode::NESTED, DisplayMode::DIALOG_ONLY],
            ]
        );
        $connection->update(
            $table,
            ['value' => '0'],
            [
                'path IN (?)' => self::PATHS,
                'value = ?' => DisplayMode::OFF,
            ]
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
