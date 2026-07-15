<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_login_does_not_link_to_existing_password_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'victim@example.com',
            'provider' => null,
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google-123');
        $socialUser->shouldReceive('getEmail')->andReturn('victim@example.com');
        $socialUser->shouldReceive('getName')->andReturn('Victim');

        Socialite::shouldReceive('driver->user')->andReturn($socialUser);

        $response = $this->get(route('oauth.callback', 'google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $existing->refresh();
        $this->assertNull($existing->provider);
    }

    public function test_registration_is_blocked_when_email_belongs_to_oauth_account(): void
    {
        User::factory()->create([
            'email' => 'oauthuser@example.com',
            'provider' => 'google',
            'provider_id' => 'google-999',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'New Person',
            'room_number' => 'A115',
            'email' => 'oauthuser@example.com',
            'password' => 'Pass1234',
            'password_confirmation' => 'Pass1234',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
