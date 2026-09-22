<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Console\Command;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Model\Garan\LabelValidator;
use CopeX\WarrantyLabel\Model\Garan\Resolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists products whose GARAN data is partially set or invalid and therefore renders no label (AC-G3).
 */
class AuditCommand extends Command
{
    public const NAME = 'copex:warranty-label:audit';

    private const OPTION_STORE = 'store';
    private const OPTION_LIMIT = 'limit';
    private const PAGE_SIZE = 500;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly LabelValidator $labelValidator,
        private readonly Config $config,
        private readonly Resolver $resolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('Lists products with incomplete or invalid EU GARAN label data')
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_REQUIRED,
                'Store ID or code whose attribute values are checked (default: admin values)',
                '0'
            )
            ->addOption(
                self::OPTION_LIMIT,
                null,
                InputOption::VALUE_REQUIRED,
                'Stop after this many problematic products (0 = no limit)',
                '0'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = trim((string) $input->getOption(self::OPTION_LIMIT));
        if (!ctype_digit($limit)) {
            $output->writeln('<error>--limit must be a non-negative integer.</error>');
            return Cli::RETURN_FAILURE;
        }

        $store = trim((string) $input->getOption(self::OPTION_STORE));
        try {
            $storeId = (int) $this->storeManager->getStore(ctype_digit($store) ? (int) $store : $store)->getId();
        } catch (NoSuchEntityException $exception) {
            $output->writeln(sprintf('<error>Store "%s" does not exist.</error>', $store));
            return Cli::RETURN_FAILURE;
        }

        [$checked, $rows] = $this->appState->emulateAreaCode(
            Area::AREA_ADMINHTML,
            fn (): array => $this->collectRows($storeId, (int) $limit)
        );

        if ($rows !== []) {
            $table = new Table($output);
            $table->setHeaders(['SKU', 'ID', 'Reasons'])->setRows($rows)->render();
        }
        $output->writeln(sprintf(
            '%d product(s) with GARAN data checked in store %d, %d with incomplete or invalid data.',
            $checked,
            $storeId,
            count($rows)
        ));
        if ((int) $limit > 0 && count($rows) >= (int) $limit) {
            $output->writeln(sprintf('Stopped after the limit of %d problematic product(s).', (int) $limit));
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @return array{0: int, 1: list<array{0: string, 1: int, 2: string}>}
     */
    private function collectRows(int $storeId, int $limit): array
    {
        $checked = 0;
        $rows = [];
        $lastId = 0;
        do {
            $pageCount = 0;
            foreach ($this->createPage($storeId, $lastId) as $product) {
                /** @var Product $product */
                $pageCount++;
                $lastId = (int) $product->getId();
                // Through the resolver, so the audit judges the same values the storefront would print:
                // parent values of a variant and the configured fallbacks included.
                $resolved = $this->resolver->resolveValues($product, $storeId);
                $values = array_map(
                    fn (string $code): string => $this->labelValidator->normalizeText($resolved[$code] ?? null),
                    Attributes::ALL
                );
                if (implode('', $values) === '') {
                    continue;
                }

                $checked++;
                $violations = $this->labelValidator->getViolations(...$values);
                if ($violations === []) {
                    continue;
                }

                $rows[] = [(string) $product->getData('sku'), $lastId, implode(', ', $violations)];
                if ($limit > 0 && count($rows) >= $limit) {
                    return [$checked, $rows];
                }
            }
        } while ($pageCount === self::PAGE_SIZE);

        return [$checked, $rows];
    }

    /**
     * Next page of products with at least one GARAN value, keyset-paginated by entity ID.
     */
    private function createPage(int $storeId, int $afterId): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(Attributes::ALL)
            ->addAttributeToFilter('entity_id', ['gt' => $afterId])
            ->addAttributeToFilter(
                array_map(
                    static fn (string $code): array => ['attribute' => $code, 'notnull' => true],
                    Attributes::ALL
                ),
                null,
                'left'
            )
            ->setOrder('entity_id', Collection::SORT_ORDER_ASC)
            ->setPageSize(self::PAGE_SIZE)
            ->setCurPage(1);

        $excluded = $this->config->getExcludedProductTypes($storeId);
        if ($excluded !== []) {
            $collection->addAttributeToFilter('type_id', ['nin' => $excluded]);
        }

        return $collection;
    }
}
