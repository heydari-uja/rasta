<?php

namespace Activities\Auth;

use Database\Database;
use Lib\AuthGate;
use Lib\PinSecurity;
use Lib\ValidationException;
use Lib\Validator;
use Lib\WorkspaceSecurityState;

class Auth
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    protected function redirect(string $path): void
    {
        redirectTo($path);
    }

    public function home()
    {
        redirectTo('/tasks');
    }

    public function loginForm()
    {
        if (currentMember()) {
            redirectTo('/tasks');
        }
        renderTemplate('auth/login.php');
    }

    public function login(array $request)
    {
        verify_csrf($request);
        setCurrentMemberId(null);
        unset($_SESSION['admin_pin_fingerprint'], $_SESSION['member_pin_fingerprint'], $_SESSION['bootstrap_admin_setup']);

        $input = [
            'mode' => $request['mode'] ?? null,
            'pin' => PinSecurity::normalize($request['pin'] ?? null),
        ];
        try {
            $v = (new Validator($input, [
                'mode' => ['required', 'in:member,admin'],
                'pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
            ], [
                'mode.required' => 'نوع ورود را انتخاب کنید.',
                'mode.in' => 'نوع ورود معتبر نیست.',
                'pin.required' => 'پین را وارد کنید.',
                'pin.regex' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
            ]))->validate();
        } catch (ValidationException $e) {
            validationErrors($e->errors);
            redirectTo('/login');
        }

        if ($v['mode'] === 'admin') {
            if (WorkspaceSecurityState::bootstrapSetupAvailable($this->db)) {
                $bootstrapPin = PinSecurity::bootstrapPin();
                if ($bootstrapPin === null) {
                    validationErrors(['pin' => ['ورود مدیر در حال حاضر در دسترس نیست. پیکربندی راه‌اندازی را بررسی کنید.']]);
                    redirectTo('/login');
                }
                if (!hash_equals($bootstrapPin, $v['pin'])) {
                    validationErrors(['pin' => ['پین مدیر درست نیست.']]);
                    redirectTo('/login');
                }
                regenerateSession();
                $_SESSION['bootstrap_admin_setup'] = true;
                redirectTo('/security/setup');
            }

            $admins = $this->db->select("SELECT * FROM members WHERE role = 'admin'")->fetchAll();
            $admin = count($admins) === 1 ? $admins[0] : null;
            if (!$admin || empty($admin['pin_hash']) || !password_verify($v['pin'], $admin['pin_hash']) || empty($admin['active'])) {
                validationErrors(['pin' => ['پین مدیر درست نیست.']]);
                redirectTo('/login');
            }

            setCurrentMemberId((int) $admin['id']);
            regenerateSession();
            $_SESSION['admin_pin_fingerprint'] = PinSecurity::fingerprint($admin['pin_hash']);
            redirectTo('/tasks');
        }

        $memberPinHash = $this->db->select('SELECT member_pin_hash FROM workspace_security WHERE id = 1')->fetch()['member_pin_hash'] ?? null;
        if (!WorkspaceSecurityState::adminPinConfigured($this->db) || !$memberPinHash) {
            validationErrors(['pin' => ['ورود اعضا هنوز فعال نشده است. مدیر باید ابتدا رمز مشترک اعضا را از بخش امنیت تنظیم کند.']]);
            redirectTo('/login');
        }
        if (!password_verify($v['pin'], $memberPinHash)) {
            validationErrors(['pin' => ['پین اعضا درست نیست.']]);
            redirectTo('/login');
        }

        setCurrentMemberId(null);
        regenerateSession();
        unset($_SESSION['admin_pin_fingerprint']);
        $_SESSION['member_pin_fingerprint'] = PinSecurity::fingerprint($memberPinHash);
        redirectTo('/profiles');
    }

    public function profiles()
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireSecuritySetupComplete();
        $user = currentMember();
        if ($user && ($user['role'] ?? '') === 'admin') {
            redirectTo('/tasks');
        }
        $members = $this->db->select("SELECT * FROM members WHERE active = 1 AND role = 'member' ORDER BY name")->fetchAll();
        renderTemplate('auth/profiles.php', ['members' => $members]);
    }

    public function select(array $request)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireSecuritySetupComplete();
        verify_csrf($request);

        try {
            if (!empty($request['member_id'])) {
                $v = (new Validator(['member_id' => $request['member_id']], [
                    'member_id' => ['required', 'integer', 'exists:members,id'],
                ]))->validate();
                $member = $this->db->select(
                    "SELECT * FROM members WHERE id = ? AND active = 1 AND role = 'member'",
                    [$v['member_id']]
                )->fetch();
                if (!$member) {
                    throw new ValidationException(['member_id' => ['پروفایل یافت نشد.']]);
                }
            } else {
                $v = (new Validator([
                    'name' => $request['name'] ?? null,
                    'job_title' => $request['job_title'] ?? null,
                ], [
                    'name' => ['required', 'string', 'max:100', 'unique:members,name'],
                    'job_title' => ['required', 'string', 'max:100'],
                ], [
                    'name.unique' => 'این نام پیش‌تر برای یک پروفایل استفاده شده است.',
                ]))->validate();
                $this->db->insert('members', ['name', 'job_title', 'role', 'active', 'color'], [
                    $v['name'], $v['job_title'], 'member', 1, 'project-marker-4',
                ]);
                $member = $this->db->select('SELECT * FROM members WHERE id = ?', [$this->db->lastInsertId()])->fetch();
            }
        } catch (ValidationException $e) {
            validationErrors($e->errors);
            redirectTo('/profiles');
        }

        setCurrentMemberId((int) $member['id']);
        regenerateSession();
        redirectTo('/tasks');
    }

    public function switchProfile()
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireSecuritySetupComplete();
        verify_csrf(requestPayload());
        setCurrentMemberId(null);
        regenerateSession();
        redirectTo('/profiles');
    }

    public function logout(array $request)
    {
        AuthGate::requireSharedAccess();
        verify_csrf($request);
        setCurrentMemberId(null);
        invalidateSession();
        session_start();
        redirectTo('/login');
    }

    public function securitySetupForm()
    {
        AuthGate::requireBootstrap();
        renderTemplate('auth/security-setup.php');
    }
}
