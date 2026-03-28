<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VideoIngestAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    public function test_guest_is_redirected_from_ingest_index(): void
    {
        $this->get(route('admin.ingest.index'))
            ->assertRedirect();
    }

    public function test_user_without_permission_cannot_access_ingest_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('admin.ingest.index'))
            ->assertForbidden();
    }

    public function test_user_with_access_admin_can_open_ingest_index(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        $this->actingAs($user)
            ->get(route('admin.ingest.index'))
            ->assertOk();
    }
}
