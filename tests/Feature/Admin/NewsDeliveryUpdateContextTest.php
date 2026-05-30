<?php

namespace Tests\Feature\Admin;

use App\Mail\NewsDeliveryMail;
use App\Models\Delivery;
use App\Models\NewsItem;
use App\Models\NewsItemUpdate;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewsDeliveryUpdateContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate(AdminPermissions::ACCESS);
        Permission::findOrCreate(AdminPermissions::NEWS);
    }

    public function test_first_email_dispatch_promotes_update_type_from_first_report_to_update(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Erster Versand',
            'author_id' => $user->id,
            'update_type' => 'first_report',
            'status' => 'draft',
            'is_wdr_job' => false,
            'planned_video_upload' => true,
        ]);

        $this->actingAs($user)->post(route('admin.news.send.post', $news), [
            'recipient_email' => 'redaktion@example.com',
        ])->assertRedirect();

        $this->assertSame('update', $news->fresh()->update_type);
    }

    public function test_second_send_is_forced_as_update_even_when_update_type_is_first_report(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Einsatz Köln',
            'author_id' => $user->id,
            'update_type' => 'first_report',
            'status' => 'draft',
            'is_wdr_job' => false,
        ]);

        Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'redaktion@example.com',
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
            'is_update_delivery' => false,
        ]);

        $this->actingAs($user)->post(route('admin.news.send.post', $news), [
            'recipient_email' => 'redaktion2@example.com',
        ])->assertRedirect();

        Mail::assertSent(NewsDeliveryMail::class, function (NewsDeliveryMail $mail): bool {
            return $mail->isUpdateDelivery
                && $mail->deliveryPhase === 'UPDATE';
        });
    }

    public function test_prepare_send_shows_update_context_after_prior_delivery(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Bereits versendet',
            'author_id' => $user->id,
            'update_type' => 'first_report',
            'status' => 'draft',
            'is_wdr_job' => false,
        ]);

        Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'redaktion@example.com',
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.news.send', $news))
            ->assertOk()
            ->assertSee('Update-Versand – Folgemeldung', false)
            ->assertSee('Vorschau: Das steht in der Mail', false)
            ->assertSee('name="is_update_delivery"', false);
    }

    public function test_should_treat_dispatch_as_update_when_prior_delivery_exists(): void
    {
        $news = NewsItem::create([
            'title' => 'Logik-Test',
            'update_type' => 'first_report',
        ]);

        $this->assertFalse($news->shouldTreatDispatchAsUpdate());

        Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'a@example.com',
            'expires_at' => now()->addDay(),
        ]);

        $news->refresh();

        $this->assertTrue($news->shouldTreatDispatchAsUpdate());
        $this->assertSame('UPDATE', $news->resolveDeliveryPhase(true));
    }

    public function test_update_delivery_mail_includes_einsatz_update_even_without_show_in_mail_flag(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $user->givePermissionTo([AdminPermissions::ACCESS, AdminPermissions::NEWS]);

        $news = NewsItem::create([
            'title' => 'Einsatz Bergheim',
            'author_id' => $user->id,
            'update_type' => 'update',
            'status' => 'draft',
            'is_wdr_job' => false,
            'planned_video_upload' => true,
        ]);

        $priorDelivery = Delivery::create([
            'news_item_id' => $news->id,
            'recipient_email' => 'redaktion@example.com',
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);
        $priorDelivery->created_at = now()->subHour();
        $priorDelivery->saveQuietly();

        NewsItemUpdate::create([
            'news_item_id' => $news->id,
            'type' => NewsItemUpdate::TYPE_SITUATION,
            'body' => '15:42 Uhr: Feuerwehr bestätigt Brand in Werkhalle.',
            'show_in_mail' => false,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('admin.news.send.post', $news), [
            'recipient_email' => 'redaktion2@example.com',
        ])->assertRedirect();

        Mail::assertSent(NewsDeliveryMail::class, function (NewsDeliveryMail $mail): bool {
            $html = $mail->render();

            return $mail->isUpdateDelivery
                && str_contains($html, 'Update zur Erstmeldung')
                && str_contains($html, 'Bezug zur Erstmeldung')
                && str_contains($html, 'Feuerwehr bestätigt Brand in Werkhalle')
                && (str_contains($html, 'Neues Material laden') || str_contains($html, 'Medienpaket öffnen'))
                && ! str_contains($html, 'Zusatz vom Redakteur')
                && ! str_contains($html, 'Neu in dieser Mail')
                && ! str_contains($html, 'Sie haben zu diesem Einsatz bereits');
        });
    }
}
