<?php

declare(strict_types=1);

namespace WPRC\Core\Data\Assets;

defined('ABSPATH') || exit;

final class AssetData
{
    public function __construct(
        public readonly string $uid,
        public readonly string $type,
        public readonly ?string $name,
        public readonly ?string $serialNumber,
        public readonly ?string $manufacturerUid,
        public readonly ?string $modelUid,
        public readonly ?string $controllerUid,
        public readonly ?string $controllerSerialNumber,
        public readonly ?string $ownerSource,
        public readonly ?string $ownerCompanyId,
        public readonly ?string $siteAddressId,
        public readonly ?string $contactId,
        public readonly string $ownershipType,
        public readonly string $status,
        public readonly ?int $yearManufactured,
        public readonly ?string $softwareVersion,
        public readonly ?string $commissionedOn
    ) {
    }
}
