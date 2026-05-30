<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\NewsItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KoelnimageFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
        Storage::fake('public');
    }

    private function koelnimageBrand(): Brand
    {
        return Brand::query()->updateOrCreate(
            ['key' => 'koelnimage'],
            [
                'name' => 'KoelnImage',
                'primary_host' => 'koelnimage.de',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );
    }

    public function test_foto_create_page_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);
        $this->koelnimageBrand();

        $this->actingAs($user)
            ->get(route('admin.koelnimage.foto.create'))
            ->assertOk()
            ->assertSee('Neue Foto-Galerie', false);
    }

    public function test_foto_store_creates_draft_and_redirects_to_upload(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);
        $brand = $this->koelnimageBrand();

        $response = $this->actingAs($user)->post(route('admin.koelnimage.foto.store'), [
            'title' => '24h Nürburgring Sonntag',
            'author_credit_user_id' => $user->id,
        ]);

        $news = NewsItem::query()->where('title', '24h Nürburgring Sonntag')->firstOrFail();
        $response->assertRedirect(route('admin.koelnimage.foto.upload', $news, false));
        $this->assertSame((int) $brand->id, (int) $news->brand_id);
        $this->assertSame('draft', $news->status);
        $this->assertSame('final', $news->update_type);
        $this->assertFalse($news->is_wdr_job);
    }

    public function test_foto_upload_page_requires_koelnimage_brand(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $otherBrand = Brand::query()->firstOrCreate(
            ['key' => 'foto-test-other'],
            [
                'name' => 'Foto Test Other',
                'primary_host' => 'foto-test-other.example',
                'secondary_hosts' => [],
                'is_active' => true,
            ]
        );

        $news = NewsItem::query()->create([
            'title' => 'Nicht Kölnimage',
            'status' => 'draft',
            'author_id' => $user->id,
            'author_credit' => 'Test',
            'brand_id' => $otherBrand->id,
            'update_type' => 'final',
        ]);

        $this->actingAs($user)
            ->get(route('admin.koelnimage.foto.upload', $news))
            ->assertNotFound();
    }

    public function test_foto_single_image_upload_returns_json(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);
        $brand = $this->koelnimageBrand();

        $news = NewsItem::query()->create([
            'title' => 'Foto Upload Test',
            'status' => 'draft',
            'author_id' => $user->id,
            'author_credit' => $user->name,
            'brand_id' => $brand->id,
            'update_type' => 'final',
        ]);

        $file = UploadedFile::fake()->image('DSC00001.jpg', 2000, 1334);

        $this->actingAs($user)
            ->post(route('admin.koelnimage.foto.images.store', $news), ['image' => $file])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['media_id', 'original_name', 'images_count']);

        $this->assertSame(1, $news->images()->count());
    }

    public function test_legacy_create_koelnimage_redirects_to_foto_create(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);
        $this->koelnimageBrand();

        $this->actingAs($user)
            ->get(route('admin.news.create.koelnimage'))
            ->assertRedirect(route('admin.koelnimage.foto.create'));
    }
}
