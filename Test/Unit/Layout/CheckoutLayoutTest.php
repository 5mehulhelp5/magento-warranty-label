<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Layout;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleXMLElement;

/**
 * The checkout components may only hang on jsLayout nodes that Magento_Checkout itself defines with a component.
 * A child of a node without one is created but never attached to a parent, so it never renders and nothing reports
 * it. That is what happens to a node only a third-party theme provides once the module runs on Luma.
 */
class CheckoutLayoutTest extends TestCase
{
    private const LAYOUT_FILE = __DIR__ . '/../../../view/frontend/layout/checkout_index_index.xml';
    private const CORE_LAYOUT_FILE = '/magento/module-checkout/view/frontend/layout/checkout_index_index.xml';
    private const COMPONENT_PREFIX = 'CopeX_WarrantyLabel/';

    /** Magento_Checkout: the region every payment method renders above its place order button */
    private const BEFORE_PLACE_ORDER = [
        'checkout', 'steps', 'billing-step', 'payment', 'payments-list', 'before-place-order',
    ];

    /** Magento_Checkout: details of one item in the order summary, template region "after_details" */
    private const SUMMARY_ITEM_DETAILS = ['checkout', 'sidebar', 'summary', 'cart_items', 'details'];

    private SimpleXMLElement $components;

    protected function setUp(): void
    {
        $this->components = $this->loadComponents(self::LAYOUT_FILE);
    }

    public function testNoticeAndGaranSummarySitAboveThePlaceOrderButton(): void
    {
        $region = $this->getNode($this->components, self::BEFORE_PLACE_ORDER);

        $this->assertNotNull($region, 'payments-list > before-place-order is missing.');
        $this->assertSame(
            'CopeX_WarrantyLabel/js/view/checkout/notice',
            $this->getComponent($region, 'copex-warranty-notice')
        );
        $this->assertSame(
            'CopeX_WarrantyLabel/js/view/checkout/garan-summary',
            $this->getComponent($region, 'copex-warranty-garan-summary')
        );
    }

    public function testGaranItemUsesTheAfterDetailsRegionOfTheSummaryItem(): void
    {
        $details = $this->getNode($this->components, self::SUMMARY_ITEM_DETAILS);

        $this->assertNotNull($details, 'sidebar > summary > cart_items > details is missing.');
        $this->assertSame(
            'CopeX_WarrantyLabel/js/view/checkout/garan-item',
            $this->getComponent($details, 'copex-warranty-garan-item')
        );
        $item = $this->getChild($this->getChild($details, 'children'), 'copex-warranty-garan-item');
        $this->assertSame('after_details', (string) $this->getChild($item, 'displayArea'));
    }

    public function testEveryParentIsAComponentOfMagentoCheckout(): void
    {
        $coreFile = $this->findCoreLayoutFile();
        if ($coreFile === null) {
            $this->markTestSkipped('magento/module-checkout is not installed next to the autoloader.');
        }

        $core = $this->loadComponents($coreFile);
        $parents = $this->findParentPaths($this->components, []);
        $this->assertNotEmpty($parents, 'No component of this module found in the checkout layout.');

        foreach ($parents as $path) {
            $node = $this->getNode($core, $path);
            $label = implode(' > ', $path);

            $this->assertNotNull($node, sprintf('Magento_Checkout does not define %s.', $label));
            $this->assertNotNull(
                $this->getChild($node, 'component'),
                sprintf('%s has no component in Magento_Checkout, its children are never attached.', $label)
            );
        }
    }

    /**
     * Paths of all nodes that carry at least one component of this module among their children.
     *
     * @param list<string> $path
     * @return list<list<string>>
     */
    private function findParentPaths(SimpleXMLElement $children, array $path): array
    {
        $paths = [];

        foreach ($children->item as $item) {
            $itemPath = [...$path, (string) $item['name']];
            $component = $this->getChild($item, 'component');

            if ($component !== null && str_starts_with((string) $component, self::COMPONENT_PREFIX)) {
                $paths[implode('/', $path)] = $path;

                continue;
            }

            $grandChildren = $this->getChild($item, 'children');
            if ($grandChildren !== null) {
                $paths += array_column(
                    array_map(
                        static fn (array $found): array => [implode('/', $found), $found],
                        $this->findParentPaths($grandChildren, $itemPath)
                    ),
                    1,
                    0
                );
            }
        }

        return array_values($paths);
    }

    private function findCoreLayoutFile(): ?string
    {
        $classLoader = (new ReflectionClass(ClassLoader::class))->getFileName();
        $file = $classLoader === false ? '' : dirname($classLoader, 2) . self::CORE_LAYOUT_FILE;

        return is_file($file) ? $file : null;
    }

    private function loadComponents(string $file): SimpleXMLElement
    {
        $xml = simplexml_load_string((string) file_get_contents($file));
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $components = $xml->xpath('//argument[@name="jsLayout"]/item[@name="components"]');
        $this->assertCount(1, $components, sprintf('Expected exactly one jsLayout argument in %s.', $file));

        return $components[0];
    }

    /**
     * @param list<string> $path names of nested jsLayout components, each below the "children" of its parent
     */
    private function getNode(SimpleXMLElement $components, array $path): ?SimpleXMLElement
    {
        $node = $components;

        foreach ($path as $index => $name) {
            $node = $this->getChild($index === 0 ? $node : $this->getChild($node, 'children'), $name);
        }

        return $node;
    }

    private function getChild(?SimpleXMLElement $parent, string $name): ?SimpleXMLElement
    {
        if ($parent === null) {
            return null;
        }

        foreach ($parent->item as $item) {
            if ((string) $item['name'] === $name) {
                return $item;
            }
        }

        return null;
    }

    private function getComponent(SimpleXMLElement $parent, string $name): ?string
    {
        $component = $this->getChild($this->getChild($this->getChild($parent, 'children'), $name), 'component');

        return $component === null ? null : (string) $component;
    }
}
