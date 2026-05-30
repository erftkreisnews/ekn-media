<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class JobsFailedRetryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('access_admin');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access_admin');

        return $user;
    }

    public function test_guest_cannot_retry_single_failed_job(): void
    {
        $uuid = (string) Str::uuid();

        $this->post(route('admin.settings.jobs.failed.retry-one'), [
            'uuid' => $uuid,
        ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_retry_one_rejects_invalid_uuid(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->from(route('admin.settings.jobs'))
            ->post(route('admin.settings.jobs.failed.retry-one'), [
                'uuid' => 'not-a-uuid',
            ])
            ->assertRedirect(route('admin.settings.jobs'))
            ->assertSessionHasErrors('uuid');
    }

    public function test_admin_can_post_retry_one_with_valid_failed_job_uuid(): void
    {
        $user = $this->adminUser();
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Tests\\Feature\\DummyFailedJob']),
            'exception' => 'Feature-Test-Ausnahme',
            'failed_at' => now(),
        ]);

        $this->assertSame(1, (int) DB::table('failed_jobs')->where('uuid', $uuid)->count());

        $this->actingAs($user)
            ->from(route('admin.settings.jobs'))
            ->post(route('admin.settings.jobs.failed.retry-one'), [
                'uuid' => $uuid,
            ])
            ->assertRedirect(route('admin.settings.jobs'))
            ->assertSessionHas('status');

        $this->assertSame(0, (int) DB::table('failed_jobs')->where('uuid', $uuid)->count());
    }
}
