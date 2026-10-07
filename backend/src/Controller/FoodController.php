<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\DayRepository;

final class FoodController
{
    /**
     * GET /api/foods/suggestions?q=&limit=
     * Earlier food entries grouped by text (case and surrounding spaces ignored),
     * most frequent first, with the nutrients of the latest entry that has some.
     */
    public function suggestions(Request $request): void
    {
        $q = trim((string) ($request->query['q'] ?? ''));
        $limit = max(1, min(20, (int) ($request->query['limit'] ?? 8)));

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE description LIKE ? ESCAPE '\\\\'";
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }

        $groups = Database::all(
            "SELECT LOWER(TRIM(description)) AS norm, COUNT(*) AS uses, MAX(created_at) AS last_used
               FROM food_entries $where
              GROUP BY norm
              ORDER BY uses DESC, last_used DESC
              LIMIT $limit",
            $params
        );

        $result = [];
        foreach ($groups as $group) {
            // prefer the latest entry with nutrients, else the latest entry at all
            $row = Database::one(
                'SELECT * FROM food_entries WHERE LOWER(TRIM(description)) = ?
                  ORDER BY calories_kcal IS NULL, created_at DESC, id DESC LIMIT 1',
                [$group['norm']]
            );
            $food = DayRepository::mapFood($row);
            $result[] = [
                'source_id' => $food['id'],
                'description' => $food['description'],
                'uses' => (int) $group['uses'],
                'has_nutrients' => $food['calories_kcal'] !== null,
            ] + array_intersect_key($food, array_flip(DayRepository::NUTRIENTS));
        }

        Response::json($result);
    }
}
