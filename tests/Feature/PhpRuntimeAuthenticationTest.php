<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhpRuntimeAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never run schema operations against the real MySQL database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('identity_number')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('role');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_all_roles_can_login_by_email_and_identity_then_logout(): void
    {
        foreach (['admin', 'mahasiswa', 'dosen', 'kaprodi', 'koordinator_ta'] as $role) {
            $user = User::create([
                'name' => 'Runtime Test',
                'email' => $role.'@example.test',
                'identity_number' => 'TEST-'.$role,
                'role' => $role,
                'password' => 'Runtime-test-password-2026!',
            ]);

            foreach ([$user->email, $user->identity_number] as $login) {
                $this->post('/login', [
                    'login' => $login,
                    'password' => 'Runtime-test-password-2026!',
                ])->assertRedirect('/dashboard');
                $this->assertAuthenticatedAs($user);

                $this->post('/logout')->assertRedirect('/');
                $this->assertGuest();
            }
        }
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::create([
            'name' => 'Runtime Test',
            'email' => 'admin@example.test',
            'identity_number' => 'TEST-ADMIN',
            'role' => 'admin',
            'password' => 'Runtime-test-password-2026!',
        ]);

        $this->from('/login')->post('/login', [
            'login' => 'TEST-ADMIN',
            'password' => 'incorrect',
        ])->assertRedirect('/login')->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
