<?php

declare(strict_types=1);

namespace WPRC\Core\Data\ERP;

defined('ABSPATH') || exit;

final readonly class QuotationTemplateData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $type = ''
    ) {
    }
}
