<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsBlocktextParseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_cannot_parse_blocktext(): void
    {
        $this->postJson(route('admin.news.parse-blocktext'), [
            'text' => "Titel: A\n\nDachzeile: B\n",
        ])->assertUnauthorized();
    }

    public function test_admin_receives_form_fields_json(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $text = <<<'TXT'
Titel: Testtitel Lang

Dachzeile: Dach hier

Webtext: Der eigentliche Text.

Metadaten:
Stadt: Bonn
Status: Entwurf
TXT;

        $this->actingAs($user)
            ->postJson(route('admin.news.parse-blocktext'), ['text' => $text])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fields.title', 'Testtitel Lang')
            ->assertJsonPath('fields.teaser', 'Dach hier')
            ->assertJsonPath('fields.body', 'Der eigentliche Text.')
            ->assertJsonPath('fields.city', 'Bonn')
            ->assertJsonPath('fields.status', 'draft');
    }
}
