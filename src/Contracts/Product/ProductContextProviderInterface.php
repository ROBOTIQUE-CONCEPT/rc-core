<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\Product;

use WPRC\Core\Data\Product\ProductContext;

defined('ABSPATH') || exit;

interface ProductContextProviderInterface
{
    public function getCurrent(): ?ProductContext;
    public function getById(int $productId): ?ProductContext;
}
