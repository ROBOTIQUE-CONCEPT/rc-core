<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\Assets;

use WPRC\Core\Data\Assets\ControllerData;
use WPRC\Core\Data\Assets\TechnicalModelData;

defined('ABSPATH') || exit;

/**
 * Optional cross-module catalog contract for active technical references.
 *
 * Modules consume this through Core ServiceRegistry. They never import or call
 * RC Assets directly.
 */
interface TechnicalReferenceCatalogInterface
{
    /** @return TechnicalModelData[] */
    public function robotModels(bool $activeOnly = true): array;

    /** @return ControllerData[] */
    public function controllers(bool $activeOnly = true): array;
}
