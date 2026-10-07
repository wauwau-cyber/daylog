<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
    ) {}

    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input') ?: '';
        $body = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($body)) {
            throw new HttpException('Invalid JSON body');
        }

        return new self(
            $_SERVER['REQUEST_METHOD'],
            rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/'),
            $_GET,
            $body,
        );
    }

    public function string(string $key, int $maxLength = 255): string
    {
        $value = trim((string) ($this->body[$key] ?? ''));
        if ($value === '') {
            throw new HttpException("Field '$key' is required");
        }
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException("Field '$key' is too long (max $maxLength characters)");
        }
        return $value;
    }

    public function number(string $key, float $min, float $max): float
    {
        $value = $this->body[$key] ?? null;
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw new HttpException("Field '$key' must be a number between $min and $max");
        }
        return (float) $value;
    }
}
