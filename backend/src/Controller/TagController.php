<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;

final class TagController
{
    public function index(Request $request): void
    {
        $where = ($request->query['include'] ?? '') === 'archived' ? '' : 'WHERE t.archived_at IS NULL';
        $rows = Database::all(
            "SELECT t.id, t.name, t.system_key, t.archived_at, COUNT(d.tag_id) AS day_count
               FROM tags t LEFT JOIN day_tags d ON d.tag_id = t.id
               $where
              GROUP BY t.id ORDER BY t.archived_at IS NOT NULL, t.system_key IS NULL, t.id"
        );
        Response::json(array_map([self::class, 'map'], $rows));
    }

    public function create(Request $request): void
    {
        $name = $request->string('name', 50);
        $existing = Database::one('SELECT id, archived_at FROM tags WHERE name = ?', [$name]);

        if ($existing && $existing['archived_at'] === null) {
            throw new HttpException("'$name' already exists", 409);
        }
        if ($existing) {
            Database::exec('UPDATE tags SET archived_at = NULL WHERE id = ?', [$existing['id']]);
            $id = (int) $existing['id'];
        } else {
            Database::exec('INSERT INTO tags (name) VALUES (?)', [$name]);
            $id = Database::lastId();
        }
        Response::json($this->find($id), 201);
    }

    public function restore(Request $request, string $id): void
    {
        Database::exec('UPDATE tags SET archived_at = NULL WHERE id = ?', [(int) $id]);
        Response::json($this->find((int) $id));
    }

    /** Unused tags are deleted; used ones are hidden so past days keep them. */
    public function delete(Request $request, string $id): void
    {
        $tag = $this->find((int) $id);
        if ($tag['day_count'] > 0) {
            Database::exec('UPDATE tags SET archived_at = NOW() WHERE id = ?', [(int) $id]);
            Response::json(['result' => 'archived']);
            return;
        }
        Database::exec('DELETE FROM tags WHERE id = ?', [(int) $id]);
        Response::json(['result' => 'deleted']);
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $row = Database::one(
            'SELECT t.id, t.name, t.system_key, t.archived_at, COUNT(d.tag_id) AS day_count
               FROM tags t LEFT JOIN day_tags d ON d.tag_id = t.id
              WHERE t.id = ? GROUP BY t.id',
            [$id]
        );
        if (!$row) {
            throw new HttpException('Tag not found', 404);
        }
        return self::map($row);
    }

    /** @return array<string, mixed> */
    public static function map(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'system_key' => $row['system_key'],
            'archived' => ($row['archived_at'] ?? null) !== null,
            'day_count' => (int) ($row['day_count'] ?? 0),
        ];
    }
}
