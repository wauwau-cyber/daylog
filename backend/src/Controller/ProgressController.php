<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Service\DayRepository;
use App\Service\Progress;

final class ProgressController
{
    /** GET /api/progress?date=YYYY-MM-DD: progress of every exercise as of that date. */
    public function index(Request $request): void
    {
        $date = (string) ($request->query['date'] ?? date('Y-m-d'));
        DayRepository::assertDate($date);

        $progress = Progress::forDate($date);
        $exercises = Database::all('SELECT id, name, archived_at FROM exercises ORDER BY name');

        $list = [];
        foreach ($exercises as $ex) {
            $list[] = [
                'exercise_id' => (int) $ex['id'],
                'name' => $ex['name'],
                'archived' => $ex['archived_at'] !== null,
                'progress' => $progress[(int) $ex['id']] ?? null,
            ];
        }
        Response::json($list);
    }

    /** GET /api/calendar?from=&to=: training and rest days (for the week strip). */
    public function calendar(Request $request): void
    {
        $from = (string) ($request->query['from'] ?? '');
        $to = (string) ($request->query['to'] ?? '');
        DayRepository::assertDate($from);
        DayRepository::assertDate($to);
        if ($to < $from || (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 366) {
            throw new HttpException('Invalid range');
        }

        $rows = Database::all(
            'SELECT log_date, COUNT(*) AS sets FROM exercise_sets
              WHERE log_date BETWEEN ? AND ? GROUP BY log_date',
            [$from, $to]
        );

        $days = [];
        foreach ($rows as $row) {
            $days[$row['log_date']] = ['sets' => (int) $row['sets'], 'rest_day' => false];
        }

        $restDays = Database::all(
            "SELECT d.log_date FROM day_tags d JOIN tags t ON t.id = d.tag_id
              WHERE t.system_key = 'rest_day' AND d.log_date BETWEEN ? AND ?",
            [$from, $to]
        );
        foreach ($restDays as $row) {
            $days[$row['log_date']] = ['sets' => $days[$row['log_date']]['sets'] ?? 0, 'rest_day' => true];
        }
        Response::json((object) $days);
    }
}
