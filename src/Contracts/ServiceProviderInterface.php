<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

use WPRC\Core\Container;

defined('ABSPATH') || exit;

interface ServiceProviderInterface
{
    public function register(Container $container): void;
}
