<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseNewsBlocktextRequest;
use App\Support\NewsBlocktextParser;
use Illuminate\Http\JsonResponse;

class NewsBlocktextParseController extends Controller
{
    public function __invoke(ParseNewsBlocktextRequest $request): JsonResponse
    {
        $raw = $request->validated()['text'];
        if (! NewsBlocktextParser::looksStructured($raw)) {
            return response()->json(['ok' => false, 'reason' => 'not_structured']);
        }

        $parsed = NewsBlocktextParser::parse($raw);
        if (count($parsed) < 2) {
            return response()->json(['ok' => false, 'reason' => 'too_few_fields']);
        }

        $fields = NewsBlocktextParser::toFormFields($parsed);

        return response()->json([
            'ok' => true,
            'fields' => $fields,
        ]);
    }
}
