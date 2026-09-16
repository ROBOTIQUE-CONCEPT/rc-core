<?php

declare(strict_types=1);

namespace WPRC\Core\UI;

use Closure;

defined('ABSPATH') || exit;

/** Immutable UI contribution owned by one RC module. */
final class Page
{
    private Closure $renderer;

    /**
     * @param callable(PageContext):mixed $renderer
     * @param string[] $styles Registered WordPress style handles.
     * @param string[] $scripts Registered WordPress script handles.
     */
    public function __construct(
        public readonly string $module,
        public readonly string $surface,
        public readonly string $route,
        public readonly string $label,
        public readonly string $capability,
        callable $renderer,
        public readonly int $priority = 10,
        public readonly bool $navigation = true,
        public readonly array $styles = [],
        public readonly array $scripts = [],
        public readonly ?string $navigationParent = null
    ) {
        $this->renderer = Closure::fromCallable($renderer);
    }

    public function render(PageContext $context): string
    {
        ob_start();
        $result = ($this->renderer)($context);
        $buffer = (string) ob_get_clean();

        if (is_string($result)) {
            return $buffer . $result;
        }

        return $buffer;
    }
}
