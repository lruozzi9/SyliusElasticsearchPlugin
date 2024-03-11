<?php

declare(strict_types=1);

namespace Webgriffe\SyliusElasticsearchPlugin\Builder;

use const JSON_THROW_ON_ERROR;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Twig\Environment;
use Webgriffe\SyliusElasticsearchPlugin\FilterHelper;

final readonly class TwigQueryBuilder implements QueryBuilderInterface
{
    /**
     * @param RepositoryInterface<ProductAttributeInterface> $attributeRepository
     */
    public function __construct(
        private Environment $twig,
        private LocaleContextInterface $localeContext,
        private LoggerInterface $logger,
        private RepositoryInterface $attributeRepository,
        private ProductOptionRepositoryInterface $optionRepository,
    ) {
    }

    public function buildTaxonQuery(
        TaxonInterface $taxon,
        ?int $from = null,
        ?int $size = null,
        ?array $sorting = null,
        bool $withAggregates = false,
        ?array $filters = null,
    ): array {
        $localeCode = $this->localeContext->getLocaleCode();
        $query = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/taxon/query.json.twig', [
            'taxon' => $taxon,
            'filters' => $filters ?? FilterHelper::retrieveFilters(),
            'localeCode' => $localeCode,
        ]);
        $taxonQuery = [];
        /** @var array $queryNormalized */
        $queryNormalized = json_decode($query, true, 512, JSON_THROW_ON_ERROR);
        $taxonQuery['query'] = $queryNormalized;

        if ($sorting !== null) {
            foreach ($sorting as $field => $order) {
                $sort = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/taxon/sort/' . $field . '.json.twig', [
                    'field' => $field,
                    'order' => $order,
                    'taxon' => $taxon,
                    'localeCode' => $localeCode,
                ]);
                /** @var array $sortNormalized */
                $sortNormalized = json_decode($sort, true, 512, JSON_THROW_ON_ERROR);
                $taxonQuery['sort'][] = $sortNormalized;
            }
        }
        if ($from !== null) {
            $taxonQuery['from'] = $from;
        }
        if ($size !== null) {
            $taxonQuery['size'] = $size;
        }

        if ($withAggregates) {
            $attributeAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/taxon/aggs/attributes.json.twig', [
                'taxon' => $taxon,
                'localeCode' => $localeCode,
            ]);
            /** @var array $attributeAggregationNormalized */
            $attributeAggregationNormalized = json_decode($attributeAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $translatedAttributeAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/taxon/aggs/translated-attributes.json.twig', [
                'taxon' => $taxon,
                'localeCode' => $localeCode,
            ]);
            /** @var array $translatedAttributeAggregationNormalized */
            $translatedAttributeAggregationNormalized = json_decode($translatedAttributeAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $optionAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/taxon/aggs/options.json.twig', [
                'taxon' => $taxon,
                'localeCode' => $localeCode,
            ]);
            /** @var array $optionAggregationNormalized */
            $optionAggregationNormalized = json_decode($optionAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $taxonQuery['aggs'] = array_merge(
                $attributeAggregationNormalized,
                $translatedAttributeAggregationNormalized,
                $optionAggregationNormalized,
            );
        }

        $this->logger->debug(sprintf('Built taxon query: "%s".', json_encode($taxonQuery, JSON_THROW_ON_ERROR)));

        return $taxonQuery;
    }

    public function buildSearchQuery(
        string $searchTerm,
        ?int $from = null,
        ?int $size = null,
        ?array $sorting = null,
        bool $withAggregates = false,
        ?array $filters = null,
    ): array {
        $localeCode = $this->localeContext->getLocaleCode();
        $query = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/search/query.json.twig', [
            'searchTerm' => $searchTerm,
            'filters' => $filters ?? FilterHelper::retrieveFilters(),
            'localeCode' => $localeCode,
        ]);
        $searchQuery = [];
        /** @var array $queryNormalized */
        $queryNormalized = json_decode($query, true, 512, JSON_THROW_ON_ERROR);
        $searchQuery['query'] = $queryNormalized;

        if ($sorting !== null) {
            foreach ($sorting as $field => $order) {
                $sort = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/search/sort/' . $field . '.json.twig', [
                    'field' => $field,
                    'order' => $order,
                    'searchTerm' => $searchTerm,
                    'localeCode' => $localeCode,
                ]);
                /** @var array $sortNormalized */
                $sortNormalized = json_decode($sort, true, 512, JSON_THROW_ON_ERROR);
                $searchQuery['sort'][] = $sortNormalized;
            }
        }
        if ($from !== null) {
            $searchQuery['from'] = $from;
        }
        if ($size !== null) {
            $searchQuery['size'] = $size;
        }

        if ($withAggregates) {
            $attributeAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/search/aggs/attributes.json.twig', [
                'searchTerm' => $searchTerm,
                'localeCode' => $localeCode,
            ]);
            /** @var array $attributeAggregationNormalized */
            $attributeAggregationNormalized = json_decode($attributeAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $translatedAttributeAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/search/aggs/translated-attributes.json.twig', [
                'searchTerm' => $searchTerm,
                'localeCode' => $localeCode,
            ]);
            /** @var array $translatedAttributeAggregationNormalized */
            $translatedAttributeAggregationNormalized = json_decode($translatedAttributeAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $optionAggregationRaw = $this->twig->render('@WebgriffeSyliusElasticsearchPlugin/query/search/aggs/options.json.twig', [
                'searchTerm' => $searchTerm,
                'localeCode' => $localeCode,
            ]);
            /** @var array $optionAggregationNormalized */
            $optionAggregationNormalized = json_decode($optionAggregationRaw, true, 512, JSON_THROW_ON_ERROR);

            $searchQuery['aggs'] = array_merge(
                $attributeAggregationNormalized,
                $translatedAttributeAggregationNormalized,
                $optionAggregationNormalized,
            );
        }

        $this->logger->debug(sprintf('Built search query: "%s".', json_encode($searchQuery, JSON_THROW_ON_ERROR)));

        return $searchQuery;
    }
}
