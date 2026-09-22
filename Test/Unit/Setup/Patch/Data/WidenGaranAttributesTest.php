<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Setup\Patch\Data;

use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Setup\Patch\Data\AddGaranProductAttributes;
use CopeX\WarrantyLabel\Setup\Patch\Data\WidenGaranAttributes;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;

class WidenGaranAttributesTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: mixed}>
     */
    private array $updates = [];

    /**
     * @var list<string>
     */
    private array $setupCalls = [];

    private WidenGaranAttributes $patch;

    protected function setUp(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('startSetup')->willReturnCallback(function (): AdapterInterface {
            $this->setupCalls[] = 'start';

            return $this->createMock(AdapterInterface::class);
        });
        $connection->method('endSetup')->willReturnCallback(function (): AdapterInterface {
            $this->setupCalls[] = 'end';

            return $this->createMock(AdapterInterface::class);
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);

        $eavSetup = $this->createMock(EavSetup::class);
        $eavSetup->method('updateAttribute')->willReturnCallback(
            function (string $entity, string $code, string $field, mixed $value): void {
                $this->updates[] = [$entity, $code, $field, $value];
            }
        );

        $eavSetupFactory = $this->getMockBuilder(EavSetupFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $eavSetupFactory->method('create')->willReturn($eavSetup);

        $this->patch = new WidenGaranAttributes($setup, $eavSetupFactory);
    }

    public function testApplyWidensScopeAndProductTypeForEveryAttribute(): void
    {
        $this->assertSame($this->patch, $this->patch->apply());

        $expected = [];
        foreach (Attributes::ALL as $code) {
            $expected[] = [Product::ENTITY, $code, 'is_global', ScopedAttributeInterface::SCOPE_STORE];
            $expected[] = [Product::ENTITY, $code, 'apply_to', null];
        }

        $this->assertSame($expected, $this->updates);
        $this->assertSame(['start', 'end'], $this->setupCalls);
    }

    public function testPatchRunsAfterTheAttributesExist(): void
    {
        $this->assertSame([AddGaranProductAttributes::class], WidenGaranAttributes::getDependencies());
        $this->assertSame([], $this->patch->getAliases());
    }
}
