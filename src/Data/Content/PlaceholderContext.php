<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Content;

defined('ABSPATH') || exit;

/**
 * Immutable request-local context passed to placeholder providers.
 */
final class PlaceholderContext
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public readonly ?int $postId = null,
        public readonly ?int $queriedObjectId = null,
        public readonly ?string $blockName = null,
        public readonly array $attributes = []
    ) {
    }

    /** @param array<string,mixed> $attributes */
    public static function current(?string $blockName = null, array $attributes = []): self
    {
        $postId = null;
        $currentId = get_the_ID();
        if (is_int($currentId) && $currentId > 0) {
            $postId = $currentId;
        }

        $queriedId = get_queried_object_id();

        return new self(
            $postId,
            $queriedId > 0 ? (int) $queriedId : null,
            $blockName,
            $attributes
        );
    }
}
