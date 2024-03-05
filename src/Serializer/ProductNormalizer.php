<?php

declare(strict_types=1);

namespace Webgriffe\SyliusElasticsearchPlugin\Serializer;

use RuntimeException;
use Sylius\Component\Core\Model\CatalogPromotionInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeTranslationInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionTranslationInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductOptionValueTranslationInterface;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Sylius\Component\Promotion\Model\CatalogPromotionTranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webgriffe\SyliusElasticsearchPlugin\Model\FilterableInterface;
use Webmozart\Assert\Assert;

final readonly class ProductNormalizer implements NormalizerInterface
{
    public function __construct(
        private ProductVariantResolverInterface $productVariantResolver,
    ) {
    }

    /**
     * @param ProductInterface|mixed $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $channel = $context['channel'];
        $product = $object;
        Assert::isInstanceOf($product, ProductInterface::class);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $normalizedProduct = [
            'sylius-id' => $product->getId(),
            'code' => $product->getCode(),
            'enabled' => $product->isEnabled(),
            'variant-selection-method' => $product->getVariantSelectionMethod(),
            'variant-selection-method-label' => $product->getVariantSelectionMethodLabel(),
            'created-at' => $product->getCreatedAt()?->format('c'),
            'name' => [],
            'description' => [],
            'short-description' => [],
            'slug' => [],
            'taxons' => [],
            'variants' => [],
            'default-variant' => null,
            'main-taxon' => null,
            'attributes' => [],
            'translated-attributes' => [],
            'images' => [],
        ];
        /** @var ProductTranslationInterface $productTranslation */
        foreach ($product->getTranslations() as $productTranslation) {
            $localeCode = $productTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedProduct['name'][] = [
                $localeCode => $productTranslation->getName(),
            ];
            $normalizedProduct['description'][] = [
                $localeCode => $productTranslation->getDescription(),
            ];
            $normalizedProduct['short-description'][] = [
                $localeCode => $productTranslation->getShortDescription(),
            ];
            $normalizedProduct['slug'][] = [
                $localeCode => $productTranslation->getSlug(),
            ];
        }
        $defaultVariant = $this->productVariantResolver->getVariant($product);
        if ($defaultVariant instanceof ProductVariantInterface) {
            $normalizedProduct['default-variant'] = $this->normalizeProductVariant($defaultVariant, $channel);
        }
        $mainTaxon = $product->getMainTaxon();
        if ($mainTaxon instanceof TaxonInterface) {
            $normalizedProduct['main-taxon'] = $this->normalizeTaxon($mainTaxon);
        }
        foreach ($product->getProductTaxons() as $productTaxon) {
            $normalizedProduct['taxons'][] = $this->normalizeProductTaxon($productTaxon);
        }
        /** @var ProductVariantInterface $variant */
        foreach ($product->getVariants() as $variant) {
            $normalizedProduct['variants'][] = $this->normalizeProductVariant($variant, $channel);
        }

        /** @var array<string|int, array{attribute: ProductAttributeInterface, values: ProductAttributeValueInterface[]}> $translatedAttributes */
        $translatedAttributes = [];
        /** @var array<string|int, array{attribute: ProductAttributeInterface, values: ProductAttributeValueInterface[]}> $attributes */
        $attributes = [];

        /** @var ProductAttributeValueInterface $attributeValue */
        foreach ($product->getAttributes() as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            Assert::isInstanceOf($attribute, ProductAttributeInterface::class);

            $attributeId = $attribute->getId();
            if (!is_string($attributeId) && !is_int($attributeId)) {
                throw new RuntimeException('Attribute ID different from string or integer is not supported.');
            }

            if ($attribute->isTranslatable()) {
                if (!array_key_exists($attributeId, $translatedAttributes)) {
                    $translatedAttributes[$attributeId] = [
                        'attribute' => $attribute,
                        'values' => [],
                    ];
                }
                $translatedAttributes[$attributeId]['values'][] = $attributeValue;

                continue;
            }

            if (!array_key_exists($attributeId, $attributes)) {
                $attributes[$attributeId] = [
                    'attribute' => $attribute,
                    'values' => [],
                ];
            }
            $attributes[$attributeId]['values'][] = $attributeValue;
        }
        foreach ($translatedAttributes as $attribute) {
            $normalizedProduct['translated-attributes'][] = $this->normalizeAttribute($attribute);
        }
        foreach ($attributes as $attribute) {
            $normalizedProduct['attributes'][] = $this->normalizeAttribute($attribute);
        }
        /** @var ProductImageInterface $image */
        foreach ($product->getImages() as $image) {
            $normalizedProduct['images'][] = $this->normalizeProductImage($image);
        }

        return $normalizedProduct;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ProductInterface::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductInterface &&
            array_key_exists('type', $context) &&
            $context['type'] === 'webgriffe_sylius_elasticsearch_plugin'
        ;
    }

    private function normalizeTaxon(TaxonInterface $taxon): array
    {
        $normalizedTaxon = [
            'sylius-id' => $taxon->getId(),
            'code' => $taxon->getCode(),
            'name' => [],
        ];
        /** @var TaxonTranslationInterface $taxonTranslation */
        foreach ($taxon->getTranslations() as $taxonTranslation) {
            $localeCode = $taxonTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedTaxon['name'][] = [
                $localeCode => $taxonTranslation->getName(),
            ];
        }

        return $normalizedTaxon;
    }

    private function normalizeProductTaxon(ProductTaxonInterface $productTaxon): array
    {
        $taxon = $productTaxon->getTaxon();
        Assert::isInstanceOf($taxon, TaxonInterface::class);

        return array_merge(
            $this->normalizeTaxon($taxon),
            ['position' => $productTaxon->getPosition()],
        );
    }

    private function normalizeProductVariant(ProductVariantInterface $variant, ChannelInterface $channel): array
    {
        $normalizedVariant = [
            'sylius-id' => $variant->getId(),
            'code' => $variant->getCode(),
            'enabled' => $variant->isEnabled(),
            'position' => $variant->getPosition(),
            'weight' => $variant->getWeight(),
            'width' => $variant->getWidth(),
            'height' => $variant->getHeight(),
            'depth' => $variant->getDepth(),
            'shipping-required' => $variant->isShippingRequired(),
            'name' => [],
            'price' => $this->normalizeChannelPricing($variant->getChannelPricingForChannel($channel)),
            'options' => [],
        ];
        /** @var array<array-key, array{option: ProductOptionInterface, value: ProductOptionValueInterface}> $variantOptionsWithValue */
        $variantOptionsWithValue = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            $option = $optionValue->getOption();
            Assert::isInstanceOf($option, ProductOptionInterface::class);
            $optionId = $option->getId();
            if (!is_string($optionId) && !is_int($optionId)) {
                throw new RuntimeException('Option ID different from string or integer is not supported.');
            }
            if (array_key_exists($optionId, $variantOptionsWithValue)) {
                throw new RuntimeException('Multiple values for the same option are not supported.');
            }
            $variantOptionsWithValue[$optionId] = [
                'option' => $option,
                'value' => $optionValue,
            ];
        }
        foreach ($variantOptionsWithValue as $optionAndValue) {
            $normalizedVariant['options'][] = $this->normalizeProductOptionAndProductOptionValue(
                $optionAndValue['option'],
                $optionAndValue['value'],
            );
        }

        /** @var ProductVariantTranslationInterface $variantTranslation */
        foreach ($variant->getTranslations() as $variantTranslation) {
            $localeCode = $variantTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedVariant['name'][] = [
                $localeCode => $variantTranslation->getName(),
            ];
        }

        return $normalizedVariant;
    }

    /**
     * @param array{attribute: ProductAttributeInterface, values: ProductAttributeValueInterface[]} $attributeWithValues
     */
    private function normalizeAttribute(array $attributeWithValues): array
    {
        $attribute = $attributeWithValues['attribute'];
        $isTranslatable = $attribute->isTranslatable();
        $filterable = false;
        if ($attribute instanceof FilterableInterface) {
            $filterable = $attribute->isFilterable();
        }
        $normalizedAttributeValue = [
            'sylius-id' => $attribute->getId(),
            'code' => $attribute->getCode(),
            'type' => $attribute->getType(),
            'storage-type' => $attribute->getStorageType(),
            'position' => $attribute->getPosition(),
            'translatable' => $isTranslatable,
            'filterable' => $filterable,
            'name' => [],
            'values' => [],
        ];
        /** @var ProductAttributeTranslationInterface $attributeTranslation */
        foreach ($attribute->getTranslations() as $attributeTranslation) {
            $localeCode = $attributeTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedAttributeValue['name'][] = [
                $localeCode => $attributeTranslation->getName(),
            ];
        }
        foreach ($attributeWithValues['values'] as $attributeValue) {
            $localeCode = $attributeValue->getLocaleCode();
            if ($isTranslatable) {
                Assert::string($localeCode);
                $normalizedAttributeValue['values'][$localeCode][] = $this->normalizeAttributeValue($attributeValue);
            } else {
                $normalizedAttributeValue['values'][] = $this->normalizeAttributeValue($attributeValue);
            }
        }

        return $normalizedAttributeValue;
    }

    private function normalizeAttributeValue(ProductAttributeValueInterface $attributeValue): array
    {
        $attribute = $attributeValue->getAttribute();
        Assert::isInstanceOf($attribute, ProductAttributeInterface::class);
        $storageType = $attribute->getStorageType();
        Assert::stringNotEmpty($storageType);

        return [
            'sylius-id' => $attributeValue->getId(),
            'code' => $attributeValue->getCode(),
            'locale' => $attributeValue->getLocaleCode(),
            $storageType . '-value' => $attributeValue->getValue(),
        ];
    }

    private function normalizeProductOptionAndProductOptionValue(
        ProductOptionInterface $option,
        ProductOptionValueInterface $optionValue,
    ): array {
        $filterable = false;
        if ($option instanceof FilterableInterface) {
            $filterable = $option->isFilterable();
        }
        $normalizedOption = [
            'sylius-id' => $option->getId(),
            'code' => $option->getCode(),
            'name' => [],
            'filterable' => $filterable,
            'value' => $this->normalizeProductOptionValue($optionValue),
        ];
        /** @var ProductOptionTranslationInterface $optionTranslation */
        foreach ($option->getTranslations() as $optionTranslation) {
            $localeCode = $optionTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedOption['name'][] = [
                $localeCode => $optionTranslation->getName(),
            ];
        }

        return $normalizedOption;
    }

    private function normalizeChannelPricing(?ChannelPricingInterface $channelPricing): ?array
    {
        if ($channelPricing === null) {
            return null;
        }
        $normalizedChannelPricing = [
            'price' => $channelPricing->getPrice(),
            'original-price' => $channelPricing->getOriginalPrice(),
            'applied-promotions' => [],
        ];
        /** @var CatalogPromotionInterface $catalogPromotion */
        foreach ($channelPricing->getAppliedPromotions() as $catalogPromotion) {
            $normalizedCatalogPromotion = [
                'sylius-id' => $catalogPromotion->getId(),
                'code' => $catalogPromotion->getCode(),
                'label' => [],
                'description' => [],
            ];
            /** @var CatalogPromotionTranslationInterface $catalogPromotionTranslation */
            foreach ($catalogPromotion->getTranslations() as $catalogPromotionTranslation) {
                $localeCode = $catalogPromotionTranslation->getLocale();
                Assert::string($localeCode);
                $normalizedCatalogPromotion['label'][] = [
                    $localeCode => $catalogPromotionTranslation->getLabel(),
                ];
                $normalizedCatalogPromotion['description'][] = [
                    $localeCode => $catalogPromotionTranslation->getDescription(),
                ];
            }

            $normalizedChannelPricing['applied-promotions'][] = $normalizedCatalogPromotion;
        }

        return $normalizedChannelPricing;
    }

    private function normalizeProductImage(ProductImageInterface $image): array
    {
        $normalizedImage = [
            'sylius-id' => $image->getId(),
            'type' => $image->getType(),
            'path' => $image->getPath(),
            'variants' => [],
        ];
        foreach ($image->getProductVariants() as $productVariant) {
            $normalizedImage['variants'][] = [
                'sylius-id' => $productVariant->getId(),
                'code' => $productVariant->getCode(),
            ];
        }

        return $normalizedImage;
    }

    private function normalizeProductOptionValue(ProductOptionValueInterface $optionValue): array
    {
        $normalizedOptionValue = [
            'sylius-id' => $optionValue->getId(),
            'code' => $optionValue->getCode(),
            'value' => $optionValue->getValue(),
            'name' => [],
        ];
        /** @var ProductOptionValueTranslationInterface $optionValueTranslation */
        foreach ($optionValue->getTranslations() as $optionValueTranslation) {
            $localeCode = $optionValueTranslation->getLocale();
            Assert::string($localeCode);
            $normalizedOptionValue['name'][] = [
                $localeCode => $optionValueTranslation->getValue(),
            ];
        }

        return $normalizedOptionValue;
    }
}
