<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Setup\Patch\Data;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\Model\Source\EmailMode;
use CopeX\WarrantyLabel\Setup\Patch\Data\ConvertEmailPlacementsToMode;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConvertEmailPlacementsToModeTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private ConvertEmailPlacementsToMode $patch;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('core_config_data');

        $this->patch = new ConvertEmailPlacementsToMode($setup);
    }

    public function testBothEarlierShapesBecomeAnEmailMode(): void
    {
        $paths = [
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
            Config::XML_PATH_GARAN_PLACEMENT_PREFIX . Config::PLACEMENT_EMAIL,
        ];
        $updates = [];
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $bind, array $where) use (&$updates): int {
                $updates[] = ['bind' => $bind, 'where' => $where];

                return 1;
            }
        );

        $this->patch->apply();

        self::assertCount(2, $updates);
        self::assertSame(['value' => EmailMode::INLINE], $updates[0]['bind']);
        self::assertSame($paths, $updates[0]['where']['path IN (?)']);
        self::assertSame(
            [DisplayMode::DIRECT, DisplayMode::NESTED, DisplayMode::DIALOG_ONLY, '1'],
            $updates[0]['where']['value IN (?)']
        );
        self::assertSame(['value' => EmailMode::NO], $updates[1]['bind']);
        self::assertSame([DisplayMode::OFF, '0'], $updates[1]['where']['value IN (?)']);
    }

    public function testPatchHasNoDependenciesOrAliases(): void
    {
        self::assertSame([], ConvertEmailPlacementsToMode::getDependencies());
        self::assertSame([], $this->patch->getAliases());
    }
}
