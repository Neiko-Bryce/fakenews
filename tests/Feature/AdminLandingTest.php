<?php

namespace Tests\Feature;

use App\Models\LandingSetting;
use App\Models\User;
use App\Services\LandingFormMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_landing(): void
    {
        $this->get('/admin/landing')->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_admin_landing(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/landing')->assertForbidden();
    }

    public function test_admin_can_view_admin_landing(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/admin/landing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/landing')
            ->has('landing')
            ->has('has_custom_content')
        );
    }

    public function test_admin_can_save_landing_and_home_reflects_it(): void
    {
        $user = User::factory()->admin()->create();
        $form = app(LandingFormMapper::class)->formFromResolved(config('landing'));
        $form['hero']['headline'] = 'Custom headline from admin';

        $this->actingAs($user)
            ->put('/admin/landing', [
                'landing' => $form,
            ])
            ->assertRedirect(route('admin.landing.edit'));

        $this->assertDatabaseHas('landing_settings', [
            'id' => 1,
        ]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('landing.hero.headline', 'Custom headline from admin')
            );
    }

    public function test_admin_can_reset_landing_to_config_defaults(): void
    {
        $user = User::factory()->admin()->create();
        $payload = config('landing');
        $payload['hero']['headline'] = 'Override';

        LandingSetting::query()->create([
            'content' => $payload,
        ]);

        $this->actingAs($user)
            ->post('/admin/landing/reset')
            ->assertRedirect(route('admin.landing.edit'));

        $this->assertDatabaseCount('landing_settings', 0);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('landing.hero.headline', config('landing.hero.headline'))
            );
    }
}
