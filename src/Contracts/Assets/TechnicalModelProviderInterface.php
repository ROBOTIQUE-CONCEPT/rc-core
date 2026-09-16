<?php

declare(strict_types=1);

namespace WPRC\Core\Contracts\Assets;

use WPRC\Core\Data\Assets\ControllerData;
use WPRC\Core\Data\Assets\TechnicalModelData;

defined('ABSPATH') || exit;

/** Stable cross-module read contract for technical robot models/controllers. */
interface TechnicalModelProviderInterface
{
    public function findRobotModelByUid(string $uid): ?TechnicalModelData;

    public function findControllerByUid(string $uid): ?ControllerData;
}
