<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_update_revokes_existing_sessions_and_tokens(): void
    {
        $user = User::forceCreate(['name' => 'Old admin', 'email' => 'admin@example.test', 'password' => 'OldPassword123!', 'is_admin' => true, 'remember_token' => 'old-token']);
        $user->createToken('old client');
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->artisan('lernumi:create-admin', ['email' => 'admin@example.test', '--name' => 'New admin'])
            ->expectsQuestion('New password (at least 12 characters, letters and numbers)', 'NewPassword456!')
            ->expectsOutput('Administrator created or updated.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
        $this->assertSame('New admin', $user->name);
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_command_rejects_passwords_that_bcrypt_would_truncate(): void
    {
        $this->artisan('lernumi:create-admin', ['email' => 'new-admin@example.test'])
            ->expectsQuestion('New password (at least 12 characters, letters and numbers)', str_repeat('é', 36).'A1')
            ->expectsOutput('The password must not exceed 72 bytes.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.test']);
    }
}
