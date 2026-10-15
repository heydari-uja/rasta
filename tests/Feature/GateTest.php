<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GateTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_is_empty_and_protected(): void
    {
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('labels', 0);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->getJson('/api/workspace')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
    }

    public function test_shared_password_then_profile_selection(): void
    {
        $secret = bin2hex(random_bytes(24));
        $hash = Hash::make($secret);
        config(['uja.password_hash' => $hash]);
        $this->post('/login', ['password' => bin2hex(random_bytes(24))])->assertSessionHasErrors('password');
        $this->post('/login', ['password' => $secret])->assertRedirect('/profiles');
        $this->get('/profiles')->assertOk()->assertSee('شما کی هستید؟');
        $this->post('/profiles', ['name' => 'عضو آزمون', 'job_title' => 'طراح'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(Member::first());
        $this->post('/profile/switch')->assertRedirect('/profiles');
        $this->assertGuest();
        $this->post('/profiles', ['member_id' => Member::first()->id])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/profiles')->assertRedirect('/login');
    }

    public function test_duplicate_profile_name_is_rejected(): void
    {
        $hash = Hash::make(bin2hex(random_bytes(24)));
        config(['uja.password_hash' => $hash]);
        Member::create(['name' => 'تکراری', 'job_title' => 'طراح']);
        $this->withSession(['shared_access' => hash('sha256', $hash)])->post('/profiles', ['name' => 'تکراری', 'job_title' => 'طراح'])->assertSessionHasErrors('name');
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['password' => bin2hex(random_bytes(16))]);
        }
        $this->post('/login', ['password' => bin2hex(random_bytes(16))])->assertStatus(429);
    }

    public function test_setup_writes_only_hash_and_never_plaintext(): void
    {
        $dir = sys_get_temp_dir().'/rasta-setup-'.bin2hex(random_bytes(8));
        mkdir($dir);
        file_put_contents($dir.'/.env', "APP_NAME=Rasta\nUJA_PASSWORD_HASH=\n");
        $original = $this->app->basePath();
        $this->app->setBasePath($dir);
        $secret = bin2hex(random_bytes(24));
        try {
            $this->artisan('uja:setup')->expectsQuestion('رمز مشترک فضای کار (حداقل ۱۰ نویسه)', $secret)->expectsQuestion('تکرار رمز مشترک', $secret)->assertSuccessful();
            $contents = file_get_contents($dir.'/.env');
            $this->assertStringNotContainsString($secret, $contents);
            preg_match("/UJA_PASSWORD_HASH='([^']+)'/", $contents, $m);
            $this->assertTrue(Hash::check($secret, $m[1]));
        } finally {
            $this->app->setBasePath($original);
            unlink($dir.'/.env');
            rmdir($dir);
        }
    }
}
