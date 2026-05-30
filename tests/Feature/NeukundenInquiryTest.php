<?php

namespace Tests\Feature;

use App\Mail\NeukundenInquiryMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NeukundenInquiryTest extends TestCase
{
    public function test_neukunden_form_page_loads(): void
    {
        $response = $this->get(route('neukunden'));

        $response->assertOk();
        $response->assertSessionHas('neukunden_form_started_at');
    }

    public function test_neukunden_submission_sends_mail_after_delay(): void
    {
        Mail::fake();

        $this->get(route('neukunden'));
        sleep(4);

        $response = $this->post(route('neukunden.store'), [
            'medienhaus' => 'Test Medienhaus GmbH',
            'redaktion' => 'Online-Lokal Bonn',
            'name' => 'Max Mustermann',
            'email' => 'redaktion@example.test',
            'phone' => '',
            'message' => 'Bitte Zugang einrichten.',
            'website' => '',
        ]);

        $response->assertRedirect(route('neukunden'));
        $response->assertSessionHas('status');

        Mail::assertSent(NeukundenInquiryMail::class, function (NeukundenInquiryMail $mail) {
            return $mail->payload['email'] === 'redaktion@example.test'
                && $mail->payload['medienhaus'] === 'Test Medienhaus GmbH'
                && $mail->payload['redaktion'] === 'Online-Lokal Bonn';
        });
    }

    public function test_neukunden_honeypot_does_not_send_mail(): void
    {
        Mail::fake();

        $this->get(route('neukunden'));
        sleep(4);

        $response = $this->post(route('neukunden.store'), [
            'medienhaus' => 'Spam',
            'redaktion' => 'Spam',
            'email' => 'spam@example.test',
            'website' => 'http://evil.example',
        ]);

        $response->assertRedirect(route('neukunden'));
        Mail::assertNothingSent();
    }

    public function test_neukunden_rejects_too_fast_submit(): void
    {
        Mail::fake();

        $this->get(route('neukunden'));

        $response = $this->post(route('neukunden.store'), [
            'medienhaus' => 'Test',
            'redaktion' => 'Test',
            'email' => 'a@b.cd',
        ]);

        $response->assertSessionHasErrors('form');
        Mail::assertNothingSent();
    }
}
