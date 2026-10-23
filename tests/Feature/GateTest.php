<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\WorkspaceSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GateTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_is_empty_protected_and_has_no_member_access(): void
    {
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('labels', 0);
        $this->get('/tasks')->assertRedirect('/login');
        $this->get('/dashboard')->assertNotFound();
        $this->getJson('/api/workspace')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
        $this->post('/login', ['mode' => 'member', 'pin' => '1234'])
            ->assertSessionHasErrors('pin');
        $this->assertDatabaseCount('members', 0);
    }

    public function test_admin_bootstrap_creates_a_profile_and_stores_only_a_pin_hash(): void
    {
        $dir = sys_get_temp_dir().'/rasta-setup-'.bin2hex(random_bytes(8));
        mkdir($dir);
        file_put_contents($dir.'/.env', "APP_NAME=Rasta\nUJA_PASSWORD_HASH=legacy-value\n");
        $original = $this->app->basePath();
        $this->app->setBasePath($dir);
        $pin = '0042';

        try {
            $this->artisan('uja:setup')
                ->expectsQuestion('نام و نام خانوادگی مدیر', 'مدیر آزمون')
                ->expectsQuestion('عنوان شغلی', 'مدیر پروژه')
                ->expectsQuestion('پین مدیر (۴ تا ۱۲ رقم)', $pin)
                ->expectsQuestion('تکرار پین مدیر', $pin)
                ->assertSuccessful();

            $admin = Member::where('role', 'admin')->firstOrFail();
            $this->assertSame('مدیر آزمون', $admin->name);
            $this->assertNotSame($pin, $admin->pin_hash);
            $this->assertTrue(Hash::check($pin, $admin->pin_hash));
            $this->assertDatabaseCount('members', 1);
            $this->assertSame("APP_NAME=Rasta\nUJA_PASSWORD_HASH=legacy-value\n", file_get_contents($dir.'/.env'));
            $this->assertNull(WorkspaceSecurity::query()->whereKey(1)->value('member_pin_hash'));
            $this->assertNotNull(WorkspaceSecurity::query()->whereKey(1)->value('admin_pin_configured_at'));
        } finally {
            $this->app->setBasePath($original);
            unlink($dir.'/.env');
            rmdir($dir);
        }
    }

    public function test_invalid_bootstrap_pin_does_not_create_an_inaccessible_admin(): void
    {
        $this->artisan('uja:setup')
            ->expectsQuestion('نام و نام خانوادگی مدیر', 'مدیر آزمون')
            ->expectsQuestion('عنوان شغلی', 'مدیر')
            ->expectsQuestion('پین مدیر (۴ تا ۱۲ رقم)', '123')
            ->expectsOutputToContain('پین باید بین ۴ تا ۱۲ رقم باشد و با پین موقت راه‌اندازی متفاوت باشد.')
            ->assertFailed();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_setup_creates_a_separate_admin_and_recovery_only_resets_that_admin(): void
    {
        $member = Member::create(['name' => 'پروفایل قدیمی', 'job_title' => 'طراح']);
        $this->artisan('uja:setup')
            ->expectsQuestion('نام و نام خانوادگی مدیر', 'مدیر جداگانه')
            ->expectsQuestion('عنوان شغلی', 'مدیر پروژه')
            ->expectsQuestion('پین مدیر (۴ تا ۱۲ رقم)', '۱۲۳۴')
            ->expectsQuestion('تکرار پین مدیر', '۱۲۳۴')
            ->assertSuccessful();

        $admin = Member::where('role', 'admin')->firstOrFail();
        $this->assertSame('member', $member->fresh()->role);
        $this->assertNotSame($member->id, $admin->id);
        $this->assertSame('مدیر جداگانه', $admin->name);
        $this->assertTrue(Hash::check('1234', $admin->pin_hash));
        $this->assertSame(1, WorkspaceSecurity::adminCount());

        $this->artisan('uja:setup')
            ->expectsQuestion('پین مدیر (۴ تا ۱۲ رقم)', '5678')
            ->expectsQuestion('تکرار پین مدیر', '5678')
            ->assertSuccessful();
        $this->assertDatabaseCount('members', 2);
        $this->assertSame('member', $member->fresh()->role);
        $this->assertSame(1, WorkspaceSecurity::adminCount());
        $this->assertTrue(Hash::check('5678', $admin->fresh()->pin_hash));
    }

    public function test_member_pin_login_and_profile_selection_keep_all_profiles_as_members(): void
    {
        $admin = $this->makeAdmin('مدیر', '9876');
        WorkspaceSecurity::singleton()->update(['member_pin_hash' => Hash::make('0012')]);
        $member = Member::create(['name' => 'عضو آزمون', 'job_title' => 'طراح']);

        $this->post('/login', ['mode' => 'member', 'pin' => '۰۰۱۲'])->assertRedirect('/profiles');
        $this->get('/profiles')->assertOk()->assertSee('عضو آزمون')->assertDontSee('مدیر');
        $this->post('/profiles', ['member_id' => $admin->id])->assertSessionHasErrors('member_id');
        $this->post('/profiles', [
            'name' => 'عضو تازه',
            'job_title' => 'پژوهشگر',
            'role' => 'admin',
            'pin_hash' => 'client-controlled',
            'active' => false,
        ])->assertRedirect('/tasks');
        $created = Member::where('name', 'عضو تازه')->firstOrFail();
        $this->assertSame('member', $created->role);
        $this->assertNull($created->pin_hash);
        $this->assertTrue($created->active);
        $this->assertSame('member', $member->role);
        $this->assertSame('admin', $admin->fresh()->role);
        $this->get('/settings')->assertOk()->assertDontSee('امنیت و حریم خصوصی');
        $this->post('/profile/switch')->assertRedirect('/profiles');
        $this->assertGuest();
        $this->post('/profiles', ['member_id' => $member->id])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($member);
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/profiles')->assertRedirect('/login');
    }

    public function test_admin_login_is_independent_of_member_pin_configuration(): void
    {
        $admin = $this->makeAdmin('مدیر', '0042');

        $this->post('/login', ['mode' => 'admin', 'pin' => '9999'])->assertSessionHasErrors('pin');
        $this->post('/login', ['mode' => 'admin', 'pin' => '۰۰۴۲'])->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($admin);
        $this->getJson('/api/workspace')->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonMissingPath('user.pin_hash');
        $this->get('/settings')->assertOk()->assertSee('امنیت و حریم خصوصی');
        $this->get('/profiles')->assertRedirect('/tasks');
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['mode' => 'admin', 'pin' => '9999']);
        }
        $this->post('/login', ['mode' => 'admin', 'pin' => '9999'])->assertStatus(429);
    }

    private function makeAdmin(string $name, string $pin): Member
    {
        $admin = (new Member)->forceFill([
            'name' => $name,
            'job_title' => 'مدیر',
            'role' => 'admin',
            'pin_hash' => Hash::make($pin),
        ]);
        $admin->save();

        return $admin;
    }
}
