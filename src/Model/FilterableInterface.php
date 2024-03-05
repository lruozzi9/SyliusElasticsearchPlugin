<?php

declare(strict_types=1);

namespace Webgriffe\SyliusElasticsearchPlugin\Model;

interface FilterableInterface
{
    public function isFilterable(): bool;
}
