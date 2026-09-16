<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Turnstile;

use WPRC\Core\Connectors\Turnstile\TurnstileClient;
use WPRC\Core\Contracts\LoggerInterface;
use WPRC\Core\Logging\SecurityLogRateLimiter;
use WPRC\Core\Security\RequestIpResolver;

defined('ABSPATH') || exit;

/**
 * Shared server-side validation for Turnstile-protected forms.
 */
final class TurnstileVerifier
{
    public function __construct(
        private readonly TurnstileSettings $settings,
        private readonly TurnstileClient $client,
        private readonly RequestIpResolver $ipResolver,
        private readonly SecurityLogRateLimiter $rateLimiter,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return array{success:bool,message:string,error_codes:array<int,string>} */
    public function verifyRequest(string $context): array
    {
        if (!$this->settings->enabled() || !$this->settings->isContextEnabled($context)) {
            return $this->success();
        }

        // Preserve the historical fail-open behavior when credentials are not configured.
        if (!$this->settings->isConfigured()) {
            return $this->success();
        }

        $token = isset($_POST['cf-turnstile-response'])
            ? sanitize_text_field(wp_unslash((string) $_POST['cf-turnstile-response']))
            : '';

        if ($token === '') {
            $ip = $this->ipResolver->resolve();
            if ($this->rateLimiter->allow('turnstile_missing_response', $ip, 30)) {
                $this->logger->warning(
                    'Turnstile response token is missing.',
                    'turnstile',
                    ['context' => $context],
                    'missing_response'
                );
            }

            return $this->failure(
                __('Validation anti-spam manquante.', 'rc-core'),
                ['missing-input-response']
            );
        }

        $result = $this->client->verify(
            $token,
            $this->ipResolver->resolve() ?? '',
            wp_generate_uuid4(),
            8
        );

        if ($result['transport_error'] !== null) {
            return $this->settings->failOpen()
                ? $this->success()
                : $this->failure(
                    __('La validation anti-spam a échoué. Merci de réessayer.', 'rc-core'),
                    ['request-failed']
                );
        }

        if ($result['success']) {
            return $this->success();
        }

        return $this->failure(
            __('La validation anti-spam a échoué. Merci de réessayer.', 'rc-core'),
            $result['error_codes']
        );
    }

    /** @return array{success:bool,message:string,error_codes:array<int,string>} */
    private function success(): array
    {
        return ['success' => true, 'message' => '', 'error_codes' => []];
    }

    /** @param array<int,string> $codes @return array{success:bool,message:string,error_codes:array<int,string>} */
    private function failure(string $message, array $codes): array
    {
        return ['success' => false, 'message' => $message, 'error_codes' => $codes];
    }
}
