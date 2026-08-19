<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Alice', 'email' => 'alice@example.test',
            'password' => 'desert1940', 'password_confirmation' => 'desert1940',
        ]);
        $response->assertCreated()->assertJsonPath('user.email', 'alice@example.test')->assertJsonStructure(['token']);
        $this->assertTrue(Hash::check('desert1940', User::firstOrFail()->password));
    }

    public function test_user_can_login_and_access_a_protected_endpoint(): void
    {
        User::factory()->create(['email' => 'alice@example.test', 'password' => 'desert1940']);
        $token = $this->postJson('/api/auth/login', ['email' => 'alice@example.test', 'password' => 'desert1940'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'alice@example.test');
    }

    public function test_protected_endpoint_rejects_anonymous_requests(): void
    {
        $this->getJson('/api/games')->assertUnauthorized();
    }
}
