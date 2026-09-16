<?php

declare(strict_types=1);

namespace WPRC\Core\InternalApi;

use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

final class Authenticator
{
    public function __construct(
        private readonly RequestSigner $signer,
        private readonly ReplayGuard $replayGuard
    ) {
    }

    public function authenticate(WP_REST_Request $request): true|WP_Error
    {
        $timestamp = (int) $request->get_header('x-rc-timestamp');
        $nonce = trim((string) $request->get_header('x-rc-nonce'));
        $signature = trim((string) $request->get_header('x-rc-signature'));

        if ($timestamp <= 0 || $nonce === '' || $signature === '') {
            return new WP_Error('rc_internal_auth_missing', 'Missing RC internal API authentication headers.', ['status' => 401]);
        }

        if (!$this->signer->verify(
            $signature,
            $request->get_method(),
            $request->get_route(),
            $request->get_query_params(),
            (string) $request->get_body(),
            $timestamp,
            $nonce
        )) {
            return new WP_Error('rc_internal_auth_invalid', 'Invalid RC internal API signature.', ['status' => 401]);
        }

        if (!$this->replayGuard->claim($nonce, $timestamp)) {
            return new WP_Error('rc_internal_auth_replay', 'Expired or replayed RC internal API request.', ['status' => 401]);
        }

        return true;
    }
}
