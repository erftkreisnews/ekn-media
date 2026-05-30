<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsErstmeldungValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_first_report_without_images_redirects_back_to_create_form_with_errors(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $response = $this->actingAs($user)->post(route('admin.news.store'), [
            'title' => 'Erstmeldung ohne Bilder',
            'status' => 'draft',
            'update_type' => 'first_report',
            'author_credit_user_id' => $user->id,
        ]);

        $response
            ->assertRedirect(route('admin.news.create'))
            ->assertSessionHasErrors('images');
    }
}
