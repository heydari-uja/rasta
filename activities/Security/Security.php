<?php

namespace Activities\Security;

use Database\Database;
use Lib\AuthGate;
use Lib\PinSecurity;
use Lib\ValidationException;
use Lib\Validator;
use Lib\WorkspaceSecurityState;

class Security
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    private function normalizedInput(array $request, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $request)) {
                $values[$key] = PinSecurity::normalize($request[$key]);
            }
        }
        return $values;
    }

    private function pinMessages(): array
    {
        return [
            'current_pin.required' => 'پین فعلی مدیر را وارد کنید.',
            'pin.required' => 'پین جدید را وارد کنید.',
            'pin.regex' => 'پین باید بین ۴ تا ۱۲ رقم باشد.',
            'pin.not_in' => 'پین مدیر باید با پین موقت راه‌اندازی متفاوت باشد.',
            'pin_confirmation.same' => 'تکرار پین جدید یکسان نیست.',
        ];
    }

    public function updateAdminPin(array $request)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireAdmin();
        AuthGate::requireSecuritySetupComplete();
        verify_csrf($request);

        $values = $this->normalizedInput($request, ['current_pin', 'pin', 'pin_confirmation']);
        $forbidden = PinSecurity::bootstrapPinForbidden();
        $pinRules = ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'];
        if ($forbidden) {
            $pinRules[] = 'not_in:' . implode(',', $forbidden);
        }

        try {
            $validated = (new Validator($values, [
                'current_pin' => ['required', 'string', 'max:12'],
                'pin' => $pinRules,
                'pin_confirmation' => ['required', 'string', 'same:pin'],
            ], $this->pinMessages()))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }

        $admin = currentMember();
        if (empty($admin['pin_hash']) || !password_verify($validated['current_pin'], $admin['pin_hash'])) {
            jsonError('پین فعلی مدیر درست نیست.', ['current_pin' => ['پین فعلی مدیر درست نیست.']]);
        }

        try {
            $this->db->beginTransaction();
            WorkspaceSecurityState::singleton($this->db);
            $security = $this->db->select('SELECT * FROM workspace_security WHERE id = 1 FOR UPDATE')->fetch();
            $adminRow = $this->db->select('SELECT * FROM members WHERE id = ? FOR UPDATE', [$admin['id']])->fetch();
            if (empty($adminRow['pin_hash']) || !password_verify($validated['current_pin'], $adminRow['pin_hash'])) {
                throw new ValidationException(['current_pin' => ['پین فعلی مدیر درست نیست.']]);
            }
            if (!empty($security['member_pin_hash']) && password_verify($validated['pin'], $security['member_pin_hash'])) {
                throw new ValidationException(['pin' => ['پین مدیر و پین مشترک اعضا باید متفاوت باشند.']]);
            }
            $hash = password_hash($validated['pin'], PASSWORD_DEFAULT);
            $this->db->update('members', $admin['id'], ['pin_hash'], [$hash]);
            $this->db->commit();
        } catch (ValidationException $e) {
            $this->db->rollBack();
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            jsonError('خطا در ذخیره.', [], 500);
        }

        regenerateSession();
        $_SESSION['admin_pin_fingerprint'] = PinSecurity::fingerprint($hash);
        jsonResponse(['ok' => true]);
    }

    public function updateMemberPin(array $request)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireAdmin();
        AuthGate::requireSecuritySetupComplete();
        verify_csrf($request);

        $values = $this->normalizedInput($request, ['current_pin', 'pin', 'pin_confirmation']);
        try {
            $validated = (new Validator($values, [
                'current_pin' => ['required', 'string', 'max:12'],
                'pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
                'pin_confirmation' => ['required', 'string', 'same:pin'],
            ], $this->pinMessages()))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }

        $admin = currentMember();
        if (empty($admin['pin_hash']) || !password_verify($validated['current_pin'], $admin['pin_hash'])) {
            jsonError('پین فعلی مدیر درست نیست.', ['current_pin' => ['پین فعلی مدیر درست نیست.']]);
        }

        try {
            $this->db->beginTransaction();
            WorkspaceSecurityState::singleton($this->db);
            $adminRow = $this->db->select('SELECT * FROM members WHERE id = ? FOR UPDATE', [$admin['id']])->fetch();
            if (empty($adminRow['pin_hash']) || !password_verify($validated['current_pin'], $adminRow['pin_hash'])) {
                throw new ValidationException(['current_pin' => ['پین فعلی مدیر درست نیست.']]);
            }
            if (password_verify($validated['pin'], $adminRow['pin_hash'])) {
                throw new ValidationException(['pin' => ['پین مدیر و پین مشترک اعضا باید متفاوت باشند.']]);
            }
            $hash = password_hash($validated['pin'], PASSWORD_DEFAULT);
            $this->db->update('workspace_security', 1, ['member_pin_hash'], [$hash]);
            $this->db->commit();
        } catch (ValidationException $e) {
            $this->db->rollBack();
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            jsonError('خطا در ذخیره.', [], 500);
        }

        jsonResponse(['ok' => true]);
    }

    public function completeInitialSetup(array $request)
    {
        AuthGate::requireBootstrap();
        verify_csrf($request);

        $values = $this->normalizedInput($request, [
            'admin_pin', 'admin_pin_confirmation', 'member_pin', 'member_pin_confirmation',
        ]);
        $values['name'] = $request['name'] ?? null;
        $values['job_title'] = $request['job_title'] ?? null;

        $forbidden = PinSecurity::bootstrapPinForbidden();
        $adminPinRules = ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'];
        if ($forbidden) {
            $adminPinRules[] = 'not_in:' . implode(',', $forbidden);
        }

        try {
            $validated = (new Validator($values, [
                'name' => ['required', 'string', 'max:100', 'unique:members,name'],
                'job_title' => ['required', 'string', 'max:100'],
                'admin_pin' => $adminPinRules,
                'admin_pin_confirmation' => ['required', 'string', 'same:admin_pin'],
                'member_pin' => ['required', 'string', 'regex:/\A[0-9]{4,12}\z/'],
                'member_pin_confirmation' => ['required', 'string', 'same:member_pin'],
            ], [
                'name.unique' => 'این نام پیش‌تر برای یک پروفایل استفاده شده است.',
                'admin_pin.not_in' => 'پین مدیر باید با پین موقت راه‌اندازی متفاوت باشد.',
            ]))->validate();
        } catch (ValidationException $e) {
            if (expectsJson()) {
                jsonError('اطلاعات راه‌اندازی معتبر نیست.', $e->errors);
            }
            validationErrors($e->errors);
            redirectTo('/security/setup');
        }

        if (
            PinSecurity::isValid($validated['admin_pin'] ?? null)
            && PinSecurity::isValid($validated['member_pin'] ?? null)
            && hash_equals($validated['admin_pin'], $validated['member_pin'])
        ) {
            $err = ['member_pin' => ['پین مدیر و پین مشترک اعضا باید متفاوت باشند.']];
            if (expectsJson()) {
                jsonError('اطلاعات راه‌اندازی معتبر نیست.', $err);
            }
            validationErrors($err);
            redirectTo('/security/setup');
        }

        if (!WorkspaceSecurityState::bootstrapSetupAvailable($this->db) || WorkspaceSecurityState::adminCount($this->db) !== 0) {
            abortJson(403, 'راه‌اندازی در دسترس نیست.');
        }

        try {
            $this->db->beginTransaction();
            WorkspaceSecurityState::singleton($this->db);
            $this->db->select('SELECT id FROM workspace_security WHERE id = 1 FOR UPDATE')->fetch();

            $adminHash = password_hash($validated['admin_pin'], PASSWORD_DEFAULT);
            $memberHash = password_hash($validated['member_pin'], PASSWORD_DEFAULT);
            $this->db->insert('members', ['name', 'job_title', 'role', 'active', 'pin_hash', 'color'], [
                $validated['name'], $validated['job_title'], 'admin', 1, $adminHash, 'project-marker-4',
            ]);
            $adminId = (int) $this->db->lastInsertId();
            $now = date('Y-m-d H:i:s');
            $this->db->update('workspace_security', 1, ['member_pin_hash', 'admin_pin_configured_at'], [$memberHash, $now]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            jsonError('خطا در راه‌اندازی.', [], 500);
        }

        setCurrentMemberId($adminId);
        regenerateSession();
        unset($_SESSION['bootstrap_admin_setup'], $_SESSION['member_pin_fingerprint']);
        $_SESSION['admin_pin_fingerprint'] = PinSecurity::fingerprint($adminHash);

        if (expectsJson()) {
            jsonResponse(['ok' => true, 'redirect' => '/tasks']);
        }
        redirectTo('/tasks');
    }
}
