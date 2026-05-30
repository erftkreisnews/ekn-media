<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNeukundenInquiryRequest;
use App\Mail\NeukundenInquiryMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class NeukundenController extends Controller
{
    /**
     * Interessierte Redaktionen: Zugang zum Medienportal beantragen (kein Online-Self-Service).
     */
    public function create(): View
    {
        session(['neukunden_form_started_at' => time()]);

        if (request()->routeIs('koelnimage.neukunde')) {
            return view('koelnimage.neukunde');
        }

        return view('neukunden');
    }

    public function store(StoreNeukundenInquiryRequest $request): RedirectResponse
    {
        $back = $this->neukundenRedirectRoute();

        // Honeypot: stiller Erfolg (keine Mail, keine Fehlermeldung für Bots)
        if ($request->filled('website')) {
            return redirect()->route($back)->with('status', 'Vielen Dank für Ihre Nachricht. Wir melden uns bei Ihnen.');
        }

        $to = trim((string) config('mail.neukunden_inquiry_to', ''));
        if ($to === '') {
            Log::error('neukunden_inquiry: neukunden_inquiry_to not configured');

            return redirect()->route($back)->with('error', 'Der Versand ist derzeit nicht möglich. Bitte nutzen Sie die Hotline oder versuchen Sie es später erneut.');
        }

        $payload = $request->safe()->only(['medienhaus', 'redaktion', 'name', 'email', 'phone', 'message']);

        try {
            Mail::to($to)->send(new NeukundenInquiryMail(
                $payload,
                (string) $request->ip(),
                $request->userAgent()
            ));
        } catch (\Throwable $e) {
            Log::error('neukunden_inquiry: send failed', ['message' => $e->getMessage()]);

            return redirect()->route($back)->withInput()->with('error', 'E-Mail konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.');
        }

        session()->forget('neukunden_form_started_at');

        return redirect()->route($back)->with('status', 'Vielen Dank für Ihre Anfrage. Wir melden uns bei Ihnen.');
    }

    private function neukundenRedirectRoute(): string
    {
        return request()->routeIs('koelnimage.neukunde.store') ? 'koelnimage.neukunde' : 'neukunden';
    }
}
