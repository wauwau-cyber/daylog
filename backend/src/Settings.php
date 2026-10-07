<?php
declare(strict_types=1);

namespace App;

final class Settings
{
    public static function get(string $name): ?string
    {
        $row = Database::one('SELECT value FROM settings WHERE name = ?', [$name]);
        return $row['value'] ?? null;
    }

    public static function set(string $name, ?string $value): void
    {
        Database::exec(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
    }
}
