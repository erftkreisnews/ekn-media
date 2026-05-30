<?php

namespace App\Services\Lexware;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LexwareClient
{
    public function post(string $path, array $payload): Response
    {
        return $this->request()->post($this->url($path), $payload);
    }

    public function get(string $path): Response
    {
        return $this->request()->get($this->url($path));
    }

    public function getFile(string $path): Response
    {
        return $this->request()->withHeaders([
            'Accept' => 'application/pdf,application/octet-stream',
        ])->get($this->url($path));
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('lexware.base_url', ''), '/');
        $token = trim((string) config('lexware.api_token', ''));

        if ($baseUrl === '') {
            throw new RuntimeException('Lexware ist nicht konfiguriert: base_url fehlt.');
        }
        if ($token === '') {
            throw new RuntimeException('Lexware ist nicht konfiguriert: api_token fehlt.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('lexware.timeout', 20));
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('lexware.base_url', ''), '/');
        $relative = '/'.ltrim($path, '/');

        return $baseUrl.$relative;
    }
}
