<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

defined('ABSPATH') || exit;

interface LoggerInterface
{
    public function info(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void;
    public function warning(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void;
    public function error(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void;
}
