<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\HttpException;
use App\Settings;

final class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public readonly string $model;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = self::apiKey();
        $this->model = self::model();

        if ($this->apiKey === '') {
            throw new HttpException('No Gemini API key yet. Add one under Settings.', 503);
        }
    }

    /** Key from the in-app settings, falling back to the GEMINI_API_KEY env variable. */
    public static function apiKey(): string
    {
        return Settings::get('gemini_api_key') ?? (string) getenv('GEMINI_API_KEY');
    }

    public static function model(): string
    {
        return Settings::get('gemini_model') ?: (getenv('GEMINI_MODEL') ?: 'gemini-flash-latest');
    }

    /**
     * Sends a prompt and returns the decoded JSON object the model produced.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function generateJson(string $systemInstruction, string $prompt, array $schema): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $ch = curl_init(sprintf(self::ENDPOINT, rawurlencode($this->model)));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new HttpException("Could not reach Gemini: $curlError", 502);
        }

        $body = json_decode((string) $raw, true);

        if ($status === 429) {
            throw new HttpException('Gemini rate limit reached. Wait a minute and try again.', 429);
        }
        if ($status !== 200) {
            $message = $body['error']['message'] ?? "HTTP $status";
            throw new HttpException("Gemini error: $message", 502);
        }

        $text = '';
        foreach ($body['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (empty($part['thought'])) {
                $text .= $part['text'] ?? '';
            }
        }

        $json = json_decode(trim($text), true);
        if (!is_array($json)) {
            $reason = $body['candidates'][0]['finishReason'] ?? 'unknown';
            throw new HttpException("Gemini returned no valid JSON (finish reason: $reason)", 502);
        }

        return $json;
    }
}
