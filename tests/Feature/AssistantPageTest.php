<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class AssistantPageTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public function test_luma_widget_is_on_company_pages(): void
    {
        $this->actingAs($this->user($this->company()));

        foreach (['/dashboard', '/greenhouses', '/alerts'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('id="luma-launcher"', false)
                ->assertSee('Luma');
        }
    }

    public function test_luma_widget_is_not_on_guest_pages(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('id="luma-launcher"', false);
    }

    public function test_assistant_page_and_menu_link_were_removed(): void
    {
        $this->actingAs($this->user($this->company()));

        $this->get('/assistant')->assertNotFound();
        $this->get('/dashboard')->assertDontSee('/assistant', false);
    }
}
