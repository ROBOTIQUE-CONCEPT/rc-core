<?php

declare(strict_types=1);

namespace WPRC\Core\ERP;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Registry for ERP providers.
 *
 * Consumers request contracts only. The active ERP source can be swapped later
 * without changing Catalog or Leads.
 */
final class ProviderRegistry
{
    /** @var array<string,array<string,object>> */
    private array $providers = [];

    public function __construct(private string $activeSource = 'axonaut')
    {
    }

    public function setActiveSource(string $source): void
    {
        $source = sanitize_key($source);
        if ($source === '') {
            throw new InvalidArgumentException('ERP source cannot be empty.');
        }
        $this->activeSource = $source;
    }

    public function activeSource(): string
    {
        return $this->activeSource;
    }

    public function register(string $source, string $contract, object $provider, bool $replace = false): void
    {
        $source = sanitize_key($source);
        if ($source === '' || !interface_exists($contract)) {
            throw new InvalidArgumentException('Invalid ERP provider registration.');
        }
        if (!$provider instanceof $contract) {
            throw new InvalidArgumentException(sprintf('ERP provider must implement %s.', $contract));
        }
        if (!$replace && isset($this->providers[$source][$contract])) {
            throw new InvalidArgumentException(sprintf('ERP provider already registered for %s / %s.', $source, $contract));
        }
        $this->providers[$source][$contract] = $provider;
    }

    public function has(string $contract, ?string $source = null): bool
    {
        $source = $source !== null ? sanitize_key($source) : $this->activeSource;
        return isset($this->providers[$source][$contract]);
    }

    public function get(string $contract, ?string $source = null): object
    {
        $source = $source !== null ? sanitize_key($source) : $this->activeSource;
        if (!isset($this->providers[$source][$contract])) {
            throw new InvalidArgumentException(sprintf('No ERP provider registered for %s / %s.', $source, $contract));
        }
        return $this->providers[$source][$contract];
    }
}
