<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_trust_index_page_loads(): void
    {
        $this->get('/trust-index')->assertOk();
    }

    public function test_report_and_appeal_pages_load(): void
    {
        $this->get('/trust-index/report')->assertOk();
        $this->get('/trust-index/appeal')->assertOk();
    }

    public function test_lookup_finds_localdev(): void
    {
        $this->getJson('/trust-index/lookup?q=LocalDev')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('scammer.minecraft_username', 'LocalDev');
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $this->getJson('/trust-index/lookup?q=localdev')
            ->assertOk()
            ->assertJsonPath('found', true);
    }

    public function test_lookup_returns_not_found_for_unknown_player(): void
    {
        $this->getJson('/trust-index/lookup?q=DefinitelyNotAScammer12345')
            ->assertOk()
            ->assertJsonPath('found', false);
    }

    public function test_lookup_rejects_short_query(): void
    {
        $this->getJson('/trust-index/lookup?q=a')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('message', 'too_short');
    }

    public function test_report_submission_is_stored(): void
    {
        $this->post('/trust-index/report', [
            'minecraft_username' => 'BadActor',
            'category' => 'TRADE_SCAM',
            'description' => str_repeat('Detailed scam report with enough context. ', 3),
            'evidence' => 'https://youtube.com/watch?v=example',
            'submitter_name' => 'Reporter',
            'submitter_contact' => 'discord#1234',
        ])
            ->assertRedirect(route('trust-index'))
            ->assertSessionHas('trust_index_message');

        $this->assertDatabaseHas('trust_index_submissions', [
            'type' => 'report',
            'minecraft_username' => 'BadActor',
            'category' => 'TRADE_SCAM',
            'status' => 'pending',
        ]);
    }

    public function test_appeal_submission_is_stored(): void
    {
        $this->post('/trust-index/appeal', [
            'minecraft_username' => 'LocalDev',
            'description' => str_repeat('I believe this listing was made in error because. ', 3),
            'submitter_contact' => 'local@example.com',
        ])
            ->assertRedirect(route('trust-index'))
            ->assertSessionHas('trust_index_message');

        $this->assertDatabaseHas('trust_index_submissions', [
            'type' => 'appeal',
            'minecraft_username' => 'LocalDev',
            'status' => 'pending',
        ]);
    }
}
