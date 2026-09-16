<?php

declare(strict_types=1);

namespace WPRC\Core\Logging;

use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Security\RequestIpResolver;
use WPRC\Core\Settings\Settings;
use WPRC\Core\Support\UidGenerator;

defined('ABSPATH') || exit;

final class DatabaseLogger implements LoggerInterface
{
    private string $requestId;

    public function __construct(
        private readonly LogRepository $repository,
        private readonly ContextSanitizer $sanitizer,
        private readonly Settings $settings,
        private readonly RequestIpResolver $ipResolver,
        UidGenerator $uid
    ) {
        $this->requestId = $uid->generate();
    }

    public function info(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void
    {
        $this->write('info', $message, $channel, $context, $event);
    }

    public function warning(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void
    {
        $this->write('warning', $message, $channel, $context, $event);
    }

    public function error(string $message, string $channel = 'core', mixed $context = null, ?string $event = null): void
    {
        $this->write('error', $message, $channel, $context, $event);
    }

    private function write(string $level, string $message, string $channel, mixed $context, ?string $event): void
    {
        if (!$this->settings->loggingEnabled()) {
            return;
        }

        $message = trim(wp_strip_all_tags($message));
        if ($message === '') {
            return;
        }

        $channel = sanitize_key($channel);
        $channel = $channel !== '' ? substr($channel, 0, 64) : 'core';
        $event = $event !== null ? sanitize_key($event) : '';
        $event = $event !== '' ? substr($event, 0, 128) : null;
        $context = $this->normalizeContext($context);
        $context = $this->sanitizer->sanitize($context);
        $payload = $context !== []
            ? wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            : null;

        if (is_string($payload) && strlen($payload) > 65535) {
            $payload = wp_json_encode(['_truncated' => true, 'message' => 'Log context exceeded 64 KiB.']);
        }

        $this->repository->insert([
            'level' => $level,
            'channel' => $channel,
            'event' => $event,
            'message' => function_exists('mb_substr') ? mb_substr($message, 0, 4000) : substr($message, 0, 4000),
            'context_payload' => is_string($payload) ? $payload : null,
            'site_id' => get_current_blog_id(),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->ipResolver->resolve(),
            'request_id' => $this->requestId,
            'created_at' => current_time('mysql', true),
        ]);

        /**
         * Fires after an RC Core log entry is persisted.
         *
         * @param string $level
         * @param string $message
         * @param string $channel
         * @param array<string,mixed> $context
         */
        do_action('rc_core_logged', $level, $message, $channel, $context);
    }
    /** @return array<string,mixed> */
    private function normalizeContext(mixed $context): array
    {
        if ($context === null) {
            return [];
        }
        if (is_array($context)) {
            return $context;
        }
        if (is_scalar($context)) {
            return ['value' => $context];
        }
        if (is_object($context)) {
            return ['object' => get_class($context)];
        }
        return ['type' => gettype($context)];
    }

}
