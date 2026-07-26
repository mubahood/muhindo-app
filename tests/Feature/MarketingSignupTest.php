<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_lists_real_plans_with_trial_ctas(): void
    {
        $starter = Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'price' => '49.00', 'is_active' => true]);
        Plan::factory()->create(['name' => 'Professional', 'slug' => 'professional', 'price' => '149.00', 'is_active' => true]);

        $res = $this->get('/pricing');

        $res->assertOk()
            ->assertSee('Starter')->assertSee('$49')
            ->assertSee('Sign up')
            ->assertSee(route('register', ['plan' => $starter->id]), false);
    }

    public function test_home_hero_links_to_registration(): void
    {
        $this->get('/')->assertOk()->assertSee(route('register'), false)->assertSee('Sign up');
    }

    public function test_header_shows_signup_and_signin(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee(route('admin.login'), false);
    }

    public function test_register_preselects_plan_from_query(): void
    {
        $starter = Plan::factory()->create(['name' => 'Starter', 'price' => '49.00', 'is_active' => true]);
        $pro = Plan::factory()->create(['name' => 'Professional', 'price' => '149.00', 'is_active' => true]);

        $res = $this->get(route('register', ['plan' => $pro->id]));
        $res->assertOk();
        // The chosen plan's radio is checked.
        $res->assertSee('value="'.$pro->id.'" checked', false);
    }
}
