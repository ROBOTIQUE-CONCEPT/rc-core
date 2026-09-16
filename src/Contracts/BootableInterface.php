<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts;

defined('ABSPATH') || exit;

interface BootableInterface
{
    public function init(): void;
}
