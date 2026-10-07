<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Service\GeminiClient;
use App\Settings;

final class SettingsController
{
    public function show(Request $request): void
    {
        $key = GeminiClient::apiKey();
        Response::json([
            'gemini_key_set' => $key !== '',
            // never send the key itself back to the browser
            'gemini_key_hint' => $key !== '' ? '…' . substr($key, -4) : null,
            'gemini_model' => GeminiClient::model(),
        ]);
    }

    public function save(Request $request): void
    {
        if (array_key_exists('gemini_api_key', $request->body)) {
            // strip all whitespace, incl. invisible characters that come along when copying
            $key = (string) preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', (string) $request->body['gemini_api_key']);
            // printable ASCII without spaces; the exact format is checked by Google on first use
            if ($key !== '' && !preg_match('/^[\x21-\x7E]{10,500}$/', $key)) {
                throw new HttpException('The key contains unexpected characters. Copy it again from AI Studio.');
            }
            Settings::set('gemini_api_key', $key !== '' ? $key : null);
        }

        if (array_key_exists('gemini_model', $request->body)) {
            $model = trim((string) $request->body['gemini_model']);
            if ($model !== '' && !preg_match('/^[a-z0-9.\-]{3,100}$/', $model)) {
                throw new HttpException('Invalid model name');
            }
            Settings::set('gemini_model', $model !== '' ? $model : null);
        }

        $this->show($request);
    }
}
