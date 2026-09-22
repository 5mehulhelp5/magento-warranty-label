<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Setup\Patch\Data;

use CopeX\WarrantyLabel\Model\Garan\Attributes;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The GARAN fields started out global and simple-only. They are now maintained per store view and on every product
 * type, so a configurable can carry the values its variants inherit.
 */
class WidenGaranAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach (Attributes::ALL as $code) {
            $eavSetup->updateAttribute(Product::ENTITY, $code, 'is_global', ScopedAttributeInterface::SCOPE_STORE);
            $eavSetup->updateAttribute(Product::ENTITY, $code, 'apply_to', null);
        }
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return [AddGaranProductAttributes::class];
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
