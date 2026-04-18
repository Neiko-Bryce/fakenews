<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_includes_landing_copy_from_config(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('landing.hero.headline')
            ->has('landing.footer.text')
            ->where('landing.hero.headline', config('landing.hero.headline'))
            ->where('landing.footer.text', config('landing.footer.text'))
        );
    }
}
