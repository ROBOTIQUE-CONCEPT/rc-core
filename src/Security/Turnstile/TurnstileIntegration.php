<?php

declare(strict_types=1);

namespace WPRC\Core\Security\Turnstile;

use WP_Error;
use WPRC\Core\Site\SiteContext;

defined('ABSPATH') || exit;

/** Protects selected WordPress and WooCommerce authentication forms. */
final class TurnstileIntegration
{
    public function __construct(
        private readonly TurnstileSettings $settings,
        private readonly TurnstileRenderer $renderer,
        private readonly TurnstileVerifier $verifier,
        private readonly SiteContext $sites
    ) {
    }

    public function init(): void
    {
        if (defined('RC_CATALOG_VERSION') && version_compare((string) RC_CATALOG_VERSION, '1.5.0-alpha8', '<')) {
            return;
        }

        if (!$this->settings->enabled()) {
            return;
        }

        if ($this->settings->enabledForLogin()) {
            add_action('login_form', [$this, 'renderLoginWidget']);
            add_filter('authenticate', [$this, 'validateLogin'], 30, 3);
        }

        if ($this->settings->enabledForRegistration()) {
            add_action('register_form', [$this, 'renderRegistrationWidget']);
            add_filter('registration_errors', [$this, 'validateRegistration'], 30, 3);
        }

        if ($this->settings->enabledForLostPassword()) {
            add_action('lostpassword_form', [$this, 'renderLostPasswordWidget']);
            add_action('lostpassword_post', [$this, 'validateLostPassword'], 30, 1);
        }

        if ($this->settings->enabledForComments()) {
            add_action('comment_form_after_fields', [$this, 'renderCommentWidget']);
            add_action('comment_form_logged_in_after', [$this, 'renderCommentWidget']);
            add_filter('preprocess_comment', [$this, 'validateComment'], 30, 1);
        }

        // Woo authentication belongs to the application site only. Hook names
        // can be registered before WooCommerce finishes booting.
        if ($this->sites->isApplicationSite()) {
            if ($this->settings->enabledForWooLogin()) {
                add_action('woocommerce_login_form', [$this, 'renderWooLoginWidget']);
                add_filter('woocommerce_process_login_errors', [$this, 'validateWooLogin'], 30, 3);
            }
            if ($this->settings->enabledForWooRegistration()) {
                add_action('woocommerce_register_form', [$this, 'renderWooRegistrationWidget']);
                add_filter('woocommerce_process_registration_errors', [$this, 'validateWooRegistration'], 30, 4);
            }
        }
    }

    public function renderLoginWidget(): void { echo $this->renderer->render('login', ['class' => 'wprc-turnstile--login']); }
    public function renderRegistrationWidget(): void { echo $this->renderer->render('registration', ['class' => 'wprc-turnstile--registration']); }
    public function renderLostPasswordWidget(): void { echo $this->renderer->render('lost_password', ['class' => 'wprc-turnstile--lost-password']); }
    public function renderWooLoginWidget(): void { echo $this->renderer->render('woo_login', ['class' => 'wprc-turnstile--woo-login']); }
    public function renderWooRegistrationWidget(): void { echo $this->renderer->render('woo_registration', ['class' => 'wprc-turnstile--woo-registration']); }

    public function renderCommentWidget(): void
    {
        if (is_user_logged_in() && $this->settings->skipLoggedInComments()) {
            return;
        }
        echo $this->renderer->render('comment', ['class' => 'wprc-turnstile--comment']);
    }

    public function validateLogin(mixed $user, string $username, string $password): mixed
    {
        if (!$this->isLoginPost() || $this->isPortalLoginPost()) {
            return $user;
        }
        $result = $this->verifier->verifyRequest('login');
        return $result['success'] ? $user : new WP_Error('wprc_turnstile_failed', $result['message']);
    }

    public function validateRegistration(WP_Error $errors, string $sanitizedUserLogin, string $userEmail): WP_Error
    {
        $result = $this->verifier->verifyRequest('registration');
        if (!$result['success']) {
            $errors->add('wprc_turnstile_failed', $result['message']);
        }
        return $errors;
    }

    public function validateLostPassword(WP_Error $errors): void
    {
        $result = $this->verifier->verifyRequest('lost_password');
        if (!$result['success']) {
            $errors->add('wprc_turnstile_failed', $result['message']);
        }
    }

    public function validateWooLogin(WP_Error $errors, string $username, string $password): WP_Error
    {
        if (!$this->isWooLoginPost()) {
            return $errors;
        }
        $result = $this->verifier->verifyRequest('woo_login');
        if (!$result['success']) {
            $errors->add('wprc_turnstile_failed', $result['message']);
        }
        return $errors;
    }

    public function validateWooRegistration(WP_Error $errors, string $username, string $password, string $email): WP_Error
    {
        $result = $this->verifier->verifyRequest('woo_registration');
        if (!$result['success']) {
            $errors->add('wprc_turnstile_failed', $result['message']);
        }
        return $errors;
    }

    /** @param array<string,mixed> $commentData @return array<string,mixed> */
    public function validateComment(array $commentData): array
    {
        if (is_admin() || (is_user_logged_in() && $this->settings->skipLoggedInComments())) {
            return $commentData;
        }
        $result = $this->verifier->verifyRequest('comment');
        if (!$result['success']) {
            wp_die(esc_html($result['message']), esc_html__('Validation anti-spam', 'rc-core'), ['response' => 403]);
        }
        return $commentData;
    }

    private function isLoginPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
            && isset($_POST['log']);
    }

    private function isPortalLoginPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
            && sanitize_key((string) ($_POST['rc_portal_action'] ?? '')) === 'login';
    }

    private function isWooLoginPost(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
            && (isset($_POST['login']) || isset($_POST['woocommerce-login-nonce']));
    }
}
