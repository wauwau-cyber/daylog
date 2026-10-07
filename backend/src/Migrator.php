<?php
declare(strict_types=1);

namespace App;

/**
 * Applies backend/migrations/*.sql once each, in file name order.
 * Runs on every request but only does work after an update, so a fresh install
 * or a newer version needs no manual database step.
 */
final class Migrator
{
    public static function run(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(190) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
        sort($files);
        $applied = array_column(Database::all('SELECT name FROM schema_migrations'), 'name');
        $pending = array_filter($files, fn($f) => !in_array(basename($f), $applied, true));
        if (!$pending) {
            return;
        }

        // Only one request migrates; others wait for it.
        Database::one("SELECT GET_LOCK('daylog_migrate', 30)");
        try {
            $applied = array_column(Database::all('SELECT name FROM schema_migrations'), 'name');
            foreach ($pending as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue;
                }
                foreach (self::statements((string) file_get_contents($file)) as $sql) {
                    $pdo->exec($sql);
                }
                Database::exec('INSERT INTO schema_migrations (name) VALUES (?)', [$name]);
            }
        } finally {
            Database::one("SELECT RELEASE_LOCK('daylog_migrate')");
        }
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*(?:\R|$)/', (string) $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts)));
    }
}
