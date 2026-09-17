<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * AC-N10: frontend templates initialise JavaScript only through text/x-magento-init, never inline.
 */
class NoInlineScriptTest extends TestCase
{
    private const TEMPLATE_DIR = __DIR__ . '/../../../view/frontend/templates';

    public function testTemplatesContainNoInlineScript(): void
    {
        $templates = $this->findTemplates();
        $this->assertNotEmpty($templates, 'No frontend templates found.');

        foreach ($templates as $path) {
            $this->assertSame(
                [],
                $this->findInlineScripts((string) file_get_contents($path)),
                sprintf('Inline <script> in %s', $path)
            );
        }
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function markupProvider(): array
    {
        return [
            'magento init' => ['<script type="text/x-magento-init">{}</script>', 0],
            'magento init single quotes' => ["<script type='text/x-magento-init'>{}</script>", 0],
            'plain script' => ['<script>alert(1)</script>', 1],
            'javascript type' => ['<script type="text/javascript">alert(1)</script>', 1],
            'upper case' => ['<SCRIPT src="x.js"></SCRIPT>', 1],
            'no script' => ['<div data-mage-init=\'{"a": {}}\'></div>', 0],
        ];
    }

    #[DataProvider('markupProvider')]
    public function testDetectsInlineScripts(string $markup, int $expectedViolations): void
    {
        $this->assertCount($expectedViolations, $this->findInlineScripts($markup));
    }

    /**
     * @return list<string>
     */
    private function findInlineScripts(string $content): array
    {
        preg_match_all('/<script\b([^>]*)>/i', $content, $matches);

        return array_values(array_filter(
            $matches[0],
            static fn (string $tag): bool => preg_match('/\btype\s*=\s*(["\'])text\/x-magento-init\1/i', $tag) !== 1
        ));
    }

    /**
     * @return list<string>
     */
    private function findTemplates(): array
    {
        $templates = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::TEMPLATE_DIR));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $templates[] = $file->getPathname();
            }
        }
        sort($templates);

        return $templates;
    }
}
