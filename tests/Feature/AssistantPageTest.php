<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesGreenhouseFixtures;
use Tests\Concerns\UsesInMemoryDatabase;
use Tests\TestCase;

class AssistantPageTest extends TestCase
{
    use CreatesGreenhouseFixtures;
    use UsesInMemoryDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get('/assistant')->assertRedirect('/login');
    }

    public function test_user_sees_page_and_menu_link(): void
    {
        $this->actingAs($this->user($this->company()));

        $this->get('/assistant')
            ->assertOk()
            ->assertSee('Asistente LumaTek')
            ->assertSee(route('assistant.index'));
    }

    public function test_menu_links_to_assistant_from_other_pages(): void
    {
        $this->actingAs($this->user($this->company()));

        $this->get('/dashboard')->assertOk()->assertSee(route('assistant.index'));
    }
}
