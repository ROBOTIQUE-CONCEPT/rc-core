<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

use WPRC\Core\Data\Content\PlaceholderContext;

defined('ABSPATH') || exit;

/**
 * Provides explicitly whitelisted values to the RC placeholder engine.
 *
 * Providers must remain side-effect free while resolving values. Expensive
 * external lookups should be resolved through an existing cache/repository.
 */
interface PlaceholderProviderInterface
{
    /**
     * Return the placeholder names handled by this provider, without delimiters.
     *
     * Example: ['product_name', 'product_brand'].
     *
     * @return list<string>
     */
    public function placeholders(): array;

    /**
     * Resolve a placeholder for the supplied render context.
     *
     * Returning null or an empty string produces an empty replacement unless
     * the content author supplied an explicit fallback.
     */
    public function resolve(string $placeholder, PlaceholderContext $context): string|int|float|bool|null;
}
