<?php

declare(strict_types=1);

namespace WPRC\Core\ERP;

use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

final class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
