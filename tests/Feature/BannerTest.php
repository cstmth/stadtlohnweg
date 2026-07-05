<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_banner_is_not_shown(): void
    {
        Banner::current()->update([
            'enabled' => false,
            'text_en' => 'Hello from the banner',
        ]);

        $this->get(route('home'))->assertDontSee('Hello from the banner');
    }

    public function test_enabled_banner_is_shown_on_public_pages(): void
    {
        Banner::current()->update([
            'enabled' => true,
            'color' => 'warning',
            'text_en' => 'Washer 2 is under maintenance',
        ]);

        $this->get(route('home'))->assertSee('Washer 2 is under maintenance');
    }

    public function test_admin_can_update_banner_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('pages::settings.admin')
            ->set('bannerEnabled', true)
            ->set('bannerColor', 'info')
            ->set('bannerTextDe', 'Achtung, Wartungsarbeiten')
            ->set('bannerTextEn', 'Notice: maintenance in progress')
            ->call('saveBanner');

        $banner = Banner::current();

        $this->assertTrue($banner->enabled);
        $this->assertSame('info', $banner->color);
        $this->assertSame('Achtung, Wartungsarbeiten', $banner->text_de);
        $this->assertSame('Notice: maintenance in progress', $banner->text_en);
    }

    public function test_non_admin_cannot_access_admin_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test('pages::settings.admin')
            ->assertForbidden();
    }
}
