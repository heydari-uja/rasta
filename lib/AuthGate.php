<?php

namespace Lib;

use Database\Database;

class AuthGate
{
    public static function requireSharedAccess(): void
    {
        $user = currentMember();
        $path = trim(parse_url(currentUrl(), PHP_URL_PATH) ?? '/', '/');
        $method = methodField();

        if (!$user && !empty($_SESSION['bootstrap_admin_setup'])) {
            $setup = ($method === 'GET' && $path === 'security/setup')
                || ($method === 'POST' && $path === 'api/security/setup');
            $logout = ($method === 'POST' && $path === 'logout');
            $db = new Database();
            if ($logout || (WorkspaceSecurityState::bootstrapSetupAvailable($db) && $setup)) {
                return;
            }
            if (WorkspaceSecurityState::bootstrapSetupAvailable($db)) {
                if (expectsJson()) {
                    abortJson(403, 'ابتدا راه‌اندازی امنیت را تکمیل کنید.');
                }
                redirectTo('/security/setup');
            }
            unset($_SESSION['bootstrap_admin_setup']);
            self::denyWorkspaceEntry();
        }

        if ($user && empty($user['active'])) {
            setCurrentMemberId(null);
            self::denyWorkspaceEntry('پروفایل فعال نیست.');
        }

        $db = new Database();
        if ($user && ($user['role'] ?? '') === 'admin') {
            $hash = $user['pin_hash'] ?? null;
            $fingerprint = $_SESSION['admin_pin_fingerprint'] ?? '';
        } else {
            $hash = $db->select('SELECT member_pin_hash FROM workspace_security WHERE id = 1')->fetch()['member_pin_hash'] ?? null;
            $fingerprint = $_SESSION['member_pin_fingerprint'] ?? '';
        }

        if (!$hash || !hash_equals(PinSecurity::fingerprint((string) $hash), (string) $fingerprint)) {
            setCurrentMemberId(null);
            unset($_SESSION['admin_pin_fingerprint'], $_SESSION['member_pin_fingerprint']);
            self::denyWorkspaceEntry();
        }
    }

    public static function requireUser(): void
    {
        if (!currentMember()) {
            if (expectsJson()) {
                abortJson(401, 'ابتدا پروفایل خود را انتخاب کنید.');
            }
            redirectTo('/profiles');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireUser();
        if ((currentMember()['role'] ?? '') !== 'admin') {
            abortJson(403, 'دسترسی مجاز نیست.');
        }
    }

    public static function requireBootstrap(): void
    {
        $path = trim(parse_url(currentUrl(), PHP_URL_PATH) ?? '/', '/');
        $method = methodField();
        $setup = ($method === 'GET' && $path === 'security/setup')
            || ($method === 'POST' && $path === 'api/security/setup');
        $db = new Database();

        if (
            !currentMember()
            && !empty($_SESSION['bootstrap_admin_setup'])
            && WorkspaceSecurityState::bootstrapSetupAvailable($db)
            && $setup
        ) {
            return;
        }

        $user = currentMember();
        if ($user && ($user['role'] ?? '') === 'admin' && WorkspaceSecurityState::adminPinConfigured($db)) {
            redirectTo('/tasks');
        }

        unset($_SESSION['bootstrap_admin_setup']);
        if (expectsJson()) {
            abortJson(403, 'ابتدا راه‌اندازی امنیت را تکمیل کنید.');
        }
        redirectTo('/login');
    }

    public static function requireSecuritySetupComplete(): void
    {
        $db = new Database();
        if (WorkspaceSecurityState::adminPinConfigured($db)) {
            return;
        }
        setCurrentMemberId(null);
        unset($_SESSION['admin_pin_fingerprint'], $_SESSION['member_pin_fingerprint'], $_SESSION['bootstrap_admin_setup']);
        if (expectsJson()) {
            abortJson(403, 'ابتدا راه‌اندازی امنیت مدیر را تکمیل کنید.');
        }
        redirectTo('/login');
    }

    private static function denyWorkspaceEntry(string $message = 'ابتدا وارد فضای کار شوید.'): void
    {
        if (expectsJson()) {
            abortJson(401, $message);
        }
        redirectTo('/login');
    }
}
