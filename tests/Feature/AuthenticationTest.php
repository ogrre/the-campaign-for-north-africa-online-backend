<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_without_exposing_a_bearer_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Alice', 'email' => 'alice@example.test',
            'password' => 'desert1940', 'password_confirmation' => 'desert1940',
        ]);
        $response->assertCreated()->assertJsonPath('user.email', 'alice@example.test')->assertJsonMissingPath('token');
        $this->assertTrue(Hash::check('desert1940', User::firstOrFail()->password));
    }

    public function test_user_can_login_and_access_a_protected_endpoint(): void
    {
        User::factory()->create(['email' => 'alice@example.test', 'password' => 'desert1940']);
        $this->postJson('/api/auth/login', ['email' => 'alice@example.test', 'password' => 'desert1940'])
            ->assertOk()->assertJsonMissingPath('token');
        $this->actingAs(User::where('email', 'alice@example.test')->firstOrFail())
            ->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'alice@example.test');
    }

    public function test_protected_endpoint_rejects_anonymous_requests(): void
    {
        $this->getJson('/api/games')->assertUnauthorized();
    }

    public function test_stateful_spa_login_uses_session_cookie_and_csrf(): void
    {
        User::factory()->create(['email' => 'spa@example.test', 'password' => 'desert1940']);
        $csrf = $this->get('/sanctum/csrf-cookie');
        $csrf->assertNoContent();
        $token = urldecode($csrf->getCookie('XSRF-TOKEN')->getValue());
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/login', 'X-XSRF-TOKEN' => $token])
            ->postJson('/api/auth/login', ['email' => 'spa@example.test', 'password' => 'desert1940'])
            ->assertOk()->assertJsonMissingPath('token');
        $this->withHeaders(['Origin' => 'http://localhost:5173'])->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.email', 'spa@example.test');
    }
}
