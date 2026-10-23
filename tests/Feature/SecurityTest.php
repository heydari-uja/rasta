<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_log_in_until_the_admin_sets_a_shared_pin(): void
    {
        $admin = $this->makeAdmin('0042');
        WorkspaceSecurity::singleton();

        $this->post('/login', ['mode' => 'member', 'pin' => '2468'])
            ->assertSessionHasErrors(['pin' => 'ورود اعضا هنوز فعال نشده است. مدیر باید ابتدا رمز مشترک اعضا را از بخش امنیت تنظیم کند.']);

        $this->loginAdmin('0042');
        $this->putJson('/api/security/member-pin', [
            'current_pin' => '0042',
            'pin' => '۰۰۱۲',
            'pin_confirmation' => '۰۰۱۲',
        ])->assertOk();

        $memberHash = WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash');
        $this->assertNotSame('0012', $memberHash);
        $this->assertTrue(Hash::check('0012', $memberHash));
        $this->getJson('/api/workspace')->assertOk()
            ->assertJsonPath('security.member_pin_configured', true)
            ->assertJsonMissingPath('security.member_pin_hash')
            ->assertJsonMissingPath('user.pin_hash');

        $this->post('/login', ['mode' => 'member', 'pin' => '0012'])->assertRedirect('/profiles');
    }

    public function test_bootstrap_pin_allows_only_mandatory_setup_and_stops_working_afterward(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $member = Member::create(['name' => 'هادی حیدری', 'job_title' => 'طراح']);

        $this->post('/login', ['mode' => 'admin', 'pin' => 'wrong'])
            ->assertSessionHasErrors('pin')
            ->assertSessionMissing('_old_input.pin');
        $this->post('/login', ['mode' => 'member', 'pin' => '4868'])
            ->assertSessionHasErrors('pin');
        $this->assertDatabaseCount('members', 1);

        $this->post('/login', ['mode' => 'admin', 'pin' => '4868'])
            ->assertRedirect('/security/setup')->assertSessionHas('bootstrap_admin_setup', true);
        $this->assertGuest();
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame(0, WorkspaceSecurity::adminCount());
        $this->assertFalse(WorkspaceSecurity::adminPinConfigured());

        $this->get('/security/setup')->assertOk()->assertSee('راه‌اندازی امنیت راستا')->assertSessionHas('bootstrap_admin_setup', true);
        $this->get('/tasks')->assertRedirect('/security/setup');
        $this->get('/profiles')->assertRedirect('/security/setup');
        $this->getJson('/api/workspace')->assertForbidden();
        $this->postJson('/api/tasks', [])->assertForbidden();
        $this->putJson('/api/security/admin-pin', [])->assertForbidden();

        $this->from('/security/setup')->post('/api/security/setup', [
            'name' => 'مدیر تازه',
            'job_title' => 'مدیر پروژه',
            'admin_pin' => '4868',
            'admin_pin_confirmation' => '4868',
            'member_pin' => '0012',
            'member_pin_confirmation' => '0012',
        ])->assertRedirect('/security/setup')->assertSessionHasErrors('admin_pin')
            ->assertSessionMissing('_old_input.admin_pin');
        $this->assertNull(WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash'));

        $this->from('/security/setup')->post('/api/security/setup', [
            'name' => 'مدیر تازه',
            'job_title' => 'مدیر پروژه',
            'admin_pin' => '۱۲۳۴',
            'admin_pin_confirmation' => '۱۲۳۴',
            'member_pin' => '۱۲۳۴',
            'member_pin_confirmation' => '۱۲۳۴',
        ])->assertRedirect('/security/setup')->assertSessionHasErrors('member_pin');
        $this->assertSame(0, WorkspaceSecurity::adminCount());

        $this->post('/api/security/setup', [
            'name' => 'مدیر تازه',
            'job_title' => 'مدیر پروژه',
            'admin_pin' => '۰۰۴۲',
            'admin_pin_confirmation' => '۰۰۴۲',
            'member_pin' => '۰۰۱۲',
            'member_pin_confirmation' => '۰۰۱۲',
        ])->assertRedirect('/tasks');

        $admin = Member::where('role', 'admin')->firstOrFail();
        $adminHash = $admin->fresh()->pin_hash;
        $memberHash = WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($member->id, $admin->id);
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame('هادی حیدری', $member->fresh()->name);
        $this->assertSame('مدیر تازه', $admin->name);
        $this->assertNotSame('0042', $adminHash);
        $this->assertNotSame('0012', $memberHash);
        $this->assertTrue(Hash::check('0042', $adminHash));
        $this->assertTrue(Hash::check('0012', $memberHash));
        $this->assertNotNull(WorkspaceSecurity::query()->whereKey(1)->value('admin_pin_configured_at'));
        $this->assertTrue(WorkspaceSecurity::adminPinConfigured());
        $this->assertSame(1, WorkspaceSecurity::adminCount());
        $this->assertDatabaseCount('members', 2);

        $this->post('/logout')->assertRedirect('/login');
        $this->withSession(['bootstrap_admin_setup' => true])->get('/security/setup')
            ->assertRedirect('/login')->assertSessionMissing('bootstrap_admin_setup');
        $this->post('/login', ['mode' => 'admin', 'pin' => '4868'])->assertSessionHasErrors('pin');
        $this->post('/login', ['mode' => 'admin', 'pin' => '۰۰۴۲'])->assertRedirect('/tasks');
        $this->get('/tasks')->assertOk();
        $this->get('/security/setup')->assertRedirect('/tasks');
        $this->putJson('/api/settings', ['name' => 'مدیر ویرایش شده', 'job_title' => 'مدیر ارشد'])->assertOk();
        $this->assertSame('هادی حیدری', $member->fresh()->name);
        $this->post('/login', ['mode' => 'member', 'pin' => '۰۰۱۲'])->assertRedirect('/profiles');
        $this->get('/profiles')->assertOk()->assertSee('هادی حیدری')->assertDontSee('مدیر ویرایش شده');
        $this->post('/profiles', ['member_id' => $member->id])->assertRedirect('/tasks');
        $this->putJson('/api/settings', ['name' => 'عضو ویرایش شده', 'job_title' => 'طراح ارشد'])->assertOk();
        $this->assertSame('مدیر ویرایش شده', $admin->fresh()->name);
        $this->assertSame('عضو ویرایش شده', $member->fresh()->name);
    }

    public function test_existing_pin_configuration_never_enters_bootstrap_mode(): void
    {
        $admin = $this->makeAdmin('9876');
        $adminHash = $admin->pin_hash;
        $memberHash = Hash::make('1357');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => $memberHash]);

        $this->assertTrue(WorkspaceSecurity::adminPinConfigured());
        $this->post('/login', ['mode' => 'admin', 'pin' => '4868'])->assertSessionHasErrors('pin');
        $this->post('/login', ['mode' => 'admin', 'pin' => '9876'])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($admin);
        $this->get('/tasks')->assertOk();
        $this->get('/security/setup')->assertRedirect('/tasks');
        $this->assertSame($adminHash, $admin->fresh()->pin_hash);
        $this->assertSame($memberHash, WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash'));

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['mode' => 'member', 'pin' => '1357'])->assertRedirect('/profiles');
        $this->get('/profiles')->assertOk();
        $this->assertSame($adminHash, $admin->fresh()->pin_hash);
        $this->assertSame($memberHash, WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash'));
    }

    public function test_production_requires_a_configured_bootstrap_pin_and_accepts_it_only_once(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->detectEnvironment(fn () => 'production');
        config(['uja.bootstrap_pin' => null]);
        $this->assertNull(PinSecurity::bootstrapPin());

        $this->post('/login', ['mode' => 'admin', 'pin' => '4868'])
            ->assertSessionHasErrors('pin')
            ->assertSessionMissing('bootstrap_admin_setup');
        $this->assertGuest();
        $this->assertSame(0, WorkspaceSecurity::adminCount());

        config(['uja.bootstrap_pin' => '7319']);
        $this->post('/login', ['mode' => 'admin', 'pin' => '4868'])->assertSessionHasErrors('pin');
        $this->assertGuest();
        $this->post('/login', ['mode' => 'admin', 'pin' => '7319'])
            ->assertRedirect('/security/setup')->assertSessionHas('bootstrap_admin_setup', true);
        $this->assertGuest();
        $this->assertSame(0, WorkspaceSecurity::adminCount());

        $this->post('/api/security/setup', [
            'name' => 'مدیر تولید',
            'job_title' => 'مدیر',
            'admin_pin' => '9357',
            'admin_pin_confirmation' => '9357',
            'member_pin' => '2468',
            'member_pin_confirmation' => '2468',
        ])->assertRedirect('/tasks');
        $this->assertSame(1, WorkspaceSecurity::adminCount());
        $this->assertAuthenticated();

        $this->post('/login', ['mode' => 'admin', 'pin' => '7319'])->assertSessionHasErrors('pin');
        $this->assertGuest();
        $this->assertSame(1, WorkspaceSecurity::adminCount());
    }

    public function test_login_mode_uses_only_its_own_pin_source(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = $this->makeAdmin('9357');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => Hash::make('2468')]);

        $this->post('/login', ['mode' => 'admin', 'pin' => '9357'])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($admin);
        $this->post('/login', ['mode' => 'admin', 'pin' => '2468'])
            ->assertSessionHasErrors(['pin' => 'پین مدیر درست نیست.']);
        $this->assertGuest();

        $this->post('/login', ['mode' => 'member', 'pin' => '9357'])
            ->assertSessionHasErrors(['pin' => 'پین اعضا درست نیست.']);
        $this->assertGuest();
        $this->post('/login', ['mode' => 'member', 'pin' => '2468'])->assertRedirect('/profiles');
        $this->assertGuest();
        $this->get('/profiles')->assertOk();
    }

    public function test_admin_and_member_profiles_remain_separate_across_both_login_flows(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = (new Member)->forceFill([
            'name' => 'هادی حیدری',
            'job_title' => 'مدیر',
            'role' => 'admin',
            'pin_hash' => Hash::make('9357'),
        ]);
        $admin->save();
        $ali = Member::create(['name' => 'علی رضایی', 'job_title' => 'طراح']);
        Member::create(['name' => 'سارا احمدی', 'job_title' => 'مدیر پروژه']);
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => Hash::make('2468')]);

        $this->post('/login', ['mode' => 'member', 'pin' => '2468'])->assertRedirect('/profiles');
        $this->assertGuest();
        $this->get('/profiles')->assertOk()
            ->assertSee('علی رضایی')
            ->assertSee('سارا احمدی')
            ->assertDontSee('هادی حیدری');

        $this->post('/profiles', ['member_id' => $admin->id])->assertSessionHasErrors('member_id');
        $this->assertGuest();

        $this->post('/profiles', ['member_id' => $ali->id])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($ali);
        $this->assertSame('member', auth()->user()->role);

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['mode' => 'admin', 'pin' => '9357'])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($admin);
        $this->assertSame('admin', auth()->user()->role);
        $this->assertDatabaseCount('members', 3);

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['mode' => 'member', 'pin' => '9357'])
            ->assertSessionHasErrors(['pin' => 'پین اعضا درست نیست.']);
        $this->assertGuest();

        $this->post('/login', ['mode' => 'admin', 'pin' => '2468'])
            ->assertSessionHasErrors(['pin' => 'پین مدیر درست نیست.']);
        $this->assertGuest();
        $this->assertSame(1, WorkspaceSecurity::adminCount());
    }

    public function test_member_settings_cannot_mass_assign_security_fields(): void
    {
        $admin = $this->makeAdmin('9357');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => Hash::make('2468')]);
        $member = Member::create(['name' => 'عضو امن', 'job_title' => 'طراح']);

        $this->post('/login', ['mode' => 'member', 'pin' => '2468'])->assertRedirect('/profiles');
        $this->post('/profiles', ['member_id' => $member->id])->assertRedirect('/tasks');
        $this->putJson('/api/settings', [
            'name' => 'نام جدید',
            'job_title' => 'عنوان جدید',
            'role' => 'admin',
            'pin_hash' => 'attacker-controlled',
            'active' => false,
            'remember_token' => 'attacker-token',
        ])->assertOk()->assertJsonPath('role', 'member')->assertJsonMissingPath('pin_hash');

        $this->assertSame('member', $member->fresh()->role);
        $this->assertNull($member->fresh()->pin_hash);
        $this->assertTrue($member->fresh()->active);
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame(1, WorkspaceSecurity::adminCount());
    }

    public function test_member_cannot_call_admin_pin_endpoints(): void
    {
        $admin = $this->makeAdmin('2468');
        $memberPinHash = Hash::make('1357');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => $memberPinHash]);
        $member = Member::create(['name' => 'عضو', 'job_title' => 'طراح']);

        $this->withSession(['member_pin_fingerprint' => PinSecurity::fingerprint($memberPinHash)])->actingAs($member)
            ->putJson('/api/security/admin-pin', ['current_pin' => '2468', 'pin' => '9876', 'pin_confirmation' => '9876'])
            ->assertForbidden();

        $this->post('/login', ['mode' => 'admin', 'pin' => '2468'])->assertRedirect('/tasks');
        $this->putJson('/api/security/member-pin', ['current_pin' => 'wrong', 'pin' => '9876', 'pin_confirmation' => '9876'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_pin');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_pin_update_requires_valid_current_pin_confirmation_and_length(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->makeAdmin('0042');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => Hash::make('3333')]);
        $this->loginAdmin('0042');
        $cases = [
            [['current_pin' => '0000', 'pin' => '9876', 'pin_confirmation' => '9876'], 'current_pin'],
            [['current_pin' => '0042', 'pin' => '123', 'pin_confirmation' => '123'], 'pin'],
            [['current_pin' => '0042', 'pin' => '1234567890123', 'pin_confirmation' => '1234567890123'], 'pin'],
            [['current_pin' => '0042', 'pin' => '12a4', 'pin_confirmation' => '12a4'], 'pin'],
            [['current_pin' => '0042', 'pin' => '4868', 'pin_confirmation' => '4868'], 'pin'],
            [['current_pin' => '0042', 'pin' => '3333', 'pin_confirmation' => '3333'], 'pin'],
            [['current_pin' => '0042', 'pin' => '9876', 'pin_confirmation' => '9875'], 'pin_confirmation'],
        ];

        foreach ($cases as [$payload, $field]) {
            $this->putJson('/api/security/admin-pin', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->putJson('/api/security/admin-pin', ['current_pin' => '0042', 'pin' => '9876'])
            ->assertUnprocessable()->assertJsonValidationErrors('pin_confirmation');
    }

    public function test_admin_pin_change_endpoint_is_throttled(): void
    {
        $this->makeAdmin('0042');
        $this->loginAdmin('0042');

        for ($i = 0; $i < 5; $i++) {
            $this->putJson('/api/security/admin-pin', ['current_pin' => 'wrong', 'pin' => '9876', 'pin_confirmation' => '9876'])
                ->assertUnprocessable();
        }

        $this->putJson('/api/security/admin-pin', ['current_pin' => 'wrong', 'pin' => '9876', 'pin_confirmation' => '9876'])
            ->assertStatus(429);
    }

    public function test_admin_pin_change_invalidates_old_sessions_and_keeps_current_session(): void
    {
        $admin = $this->makeAdmin('0042');
        $oldFingerprint = PinSecurity::fingerprint($admin->pin_hash);
        $this->loginAdmin('0042');

        $this->putJson('/api/security/admin-pin', [
            'current_pin' => '0042',
            'pin' => '۹۸۷۶',
            'pin_confirmation' => '۹۸۷۶',
        ])->assertOk();

        $newHash = $admin->fresh()->pin_hash;
        $this->assertTrue(Hash::check('9876', $newHash));
        $this->assertNotSame('9876', $newHash);
        $this->assertSame(PinSecurity::fingerprint($newHash), $this->app['session.store']->get('admin_pin_fingerprint'));
        $this->assertAuthenticatedAs($admin);
        $this->getJson('/api/workspace')->assertOk();

        $this->withSession(['admin_pin_fingerprint' => $oldFingerprint])
            ->getJson('/api/workspace')->assertUnauthorized();
        $this->post('/login', ['mode' => 'admin', 'pin' => '0042'])->assertSessionHasErrors('pin');
        $this->post('/login', ['mode' => 'admin', 'pin' => '9876'])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_changing_member_pin_invalidates_existing_member_sessions(): void
    {
        $admin = $this->makeAdmin('0042');
        $oldPinHash = Hash::make('0012');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => $oldPinHash]);
        $oldFingerprint = PinSecurity::fingerprint($oldPinHash);
        $member = Member::create(['name' => 'عضو آزمون', 'job_title' => 'طراح']);
        $this->loginAdmin('0042');

        $this->putJson('/api/security/member-pin', [
            'current_pin' => '0042',
            'pin' => '0042',
            'pin_confirmation' => '0042',
        ])->assertUnprocessable()->assertJsonValidationErrors('pin');

        $this->putJson('/api/security/member-pin', [
            'current_pin' => '0042',
            'pin' => '3456',
            'pin_confirmation' => '3456',
        ])->assertOk();

        $this->assertTrue(Hash::check('3456', WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash')));
        $this->withSession(['member_pin_fingerprint' => $oldFingerprint])->actingAs($member)
            ->getJson('/api/workspace')->assertUnauthorized();
        $this->post('/login', ['mode' => 'member', 'pin' => '0012'])->assertSessionHasErrors('pin');
        $this->post('/login', ['mode' => 'member', 'pin' => '3456'])->assertRedirect('/profiles');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_role_does_not_expand_private_task_visibility(): void
    {
        $admin = $this->makeAdmin('0042');
        $memberPinHash = Hash::make('2468');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => $memberPinHash]);
        $member = Member::create(['name' => 'عضو', 'job_title' => 'طراح']);
        $this->withSession(['member_pin_fingerprint' => PinSecurity::fingerprint($memberPinHash)])->actingAs($member);

        $stage = $this->postJson('/api/member-stages', ['title' => 'خصوصی'])->assertCreated()->json();
        $task = $this->postJson('/api/tasks', [
            'project_id' => null,
            'member_stage_id' => $stage['id'],
            'title' => 'تسک خصوصی',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'labels' => [],
            'subtasks' => [],
        ])->assertCreated()->json();

        $this->post('/login', ['mode' => 'admin', 'pin' => '0042'])->assertRedirect('/tasks');
        $this->getJson('/api/tasks/'.$task['id'])->assertNotFound();
        $this->assertSame('admin', $admin->fresh()->role);
    }

    private function makeAdmin(string $pin): Member
    {
        $admin = (new Member)->forceFill([
            'name' => 'مدیر آزمون',
            'job_title' => 'مدیر',
            'role' => 'admin',
            'pin_hash' => Hash::make($pin),
        ]);
        $admin->save();

        return $admin;
    }

    private function loginAdmin(string $pin): void
    {
        $this->post('/login', ['mode' => 'admin', 'pin' => $pin])->assertRedirect('/tasks');
    }
}
