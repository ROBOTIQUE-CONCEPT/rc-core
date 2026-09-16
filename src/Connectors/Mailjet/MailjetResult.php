<?php

declare(strict_types=1);

namespace WPRC\Core\Connectors\Mailjet;

defined('ABSPATH') || exit;

final class MailjetResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
        public readonly int $code = 0,
        public readonly mixed $body = null
    ) {
    }

    public static function success(int $code = 0, mixed $body = null): self
    {
        return new self(true, null, $code, $body);
    }

    public static function failure(string $error, int $code = 0, mixed $body = null): self
    {
        return new self(false, $error, $code, $body);
    }

    /** @return array{success:bool,error:?string,code:int,body:mixed} */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'code' => $this->code,
            'body' => $this->body,
        ];
    }
}
