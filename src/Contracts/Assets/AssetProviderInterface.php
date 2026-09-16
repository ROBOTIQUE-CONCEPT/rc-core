<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\Assets;

use WPRC\Core\Data\Assets\AssetData;

defined('ABSPATH') || exit;

/** Stable cross-module read contract for physical RC assets. */
interface AssetProviderInterface
{
    public function findByUid(string $uid): ?AssetData;

    /** @return AssetData[] */
    public function findByOwner(string $source, string $companyId): array;
}
