<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

defined('ABSPATH') || exit;

/** Result of resolving a portal path to the longest matching module route. */
final class PageMatch
{
    public function __construct(
        public readonly Page $page,
        public readonly string $remainder = ''
    ) {
    }
}
