<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Capabilities;

use InvalidArgumentException;
use WPRC\Core\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Registry of capabilities declared by RC modules.
 *
 * Declarations are persisted at platform level so Network Admin can manage a
 * module's permissions even when that module is active only on another site.
 */
final class CapabilityRegistry
{
    private const SETTINGS_KEY = 'capability_catalog';

    /** @var array<string,array<string,true>> */
    private array $capabilities = [];

    public function __construct(private readonly Settings $settings)
    {
        $stored = $this->settings->get(self::SETTINGS_KEY, []);
        if (!is_array($stored)) {
            return;
        }

        foreach ($stored as $module => $moduleCapabilities) {
            $module = sanitize_key((string) $module);
            if ($module === '' || !is_array($moduleCapabilities)) {
                continue;
            }
            foreach ($moduleCapabilities as $capability) {
                $capability = sanitize_key((string) $capability);
                if ($capability !== '' && str_starts_with($capability, 'rc_')) {
                    $this->capabilities[$module][$capability] = true;
                }
            }
        }
    }

    /** @param string[] $capabilities */
    public function register(string $module, array $capabilities): void
    {
        $module = sanitize_key($module);
        if ($module === '') {
            throw new InvalidArgumentException('Capability owner module cannot be empty.');
        }

        $normalized = [];
        foreach ($capabilities as $capability) {
            $capability = sanitize_key((string) $capability);
            if ($capability === '' || !str_starts_with($capability, 'rc_')) {
                throw new InvalidArgumentException('RC capabilities must use the "rc_" prefix.');
            }
            $normalized[$capability] = true;
        }

        ksort($normalized);
        $current = $this->capabilities[$module] ?? [];
        ksort($current);
        if ($current === $normalized) {
            return;
        }

        $this->capabilities[$module] = $normalized;
        $this->persist();
    }

    public function has(string $capability): bool
    {
        $capability = sanitize_key($capability);
        foreach ($this->capabilities as $moduleCapabilities) {
            if (isset($moduleCapabilities[$capability])) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    public function forModule(string $module): array
    {
        return array_keys($this->capabilities[sanitize_key($module)] ?? []);
    }

    /** @return array<string,string[]> */
    public function byModule(): array
    {
        $result = [];
        foreach ($this->capabilities as $module => $capabilities) {
            $items = array_keys($capabilities);
            sort($items);
            $result[$module] = $items;
        }
        ksort($result);
        return $result;
    }

    /** @return string[] */
    public function all(): array
    {
        $all = [];
        foreach ($this->capabilities as $moduleCapabilities) {
            $all += $moduleCapabilities;
        }
        $items = array_keys($all);
        sort($items);
        return $items;
    }

    private function persist(): void
    {
        $stored = [];
        foreach ($this->byModule() as $module => $capabilities) {
            $stored[$module] = array_values($capabilities);
        }
        $this->settings->set(self::SETTINGS_KEY, $stored);
    }
}
