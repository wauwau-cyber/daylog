<?php
declare(strict_types=1);

use App\Controller\AnalysisController;
use App\Controller\DayController;
use App\Controller\ExerciseController;
use App\Controller\FoodController;
use App\Controller\ProfileController;
use App\Controller\ProgressController;
use App\Controller\SettingsController;
use App\Controller\TagController;
use App\Controller\VersionController;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Migrator;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

date_default_timezone_set(getenv('TZ') ?: 'Europe/Berlin');

header('Access-Control-Allow-Origin: http://localhost:47200');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$date = '(\d{4}-\d{2}-\d{2})';
$id = '(\d+)';

$router = new Router();
$router->get('/api/profile', [ProfileController::class, 'show']);
$router->post('/api/profile', [ProfileController::class, 'save']);

$router->get("/api/days/$date", [DayController::class, 'show']);
$router->put("/api/days/$date/weight", [DayController::class, 'saveWeight']);
$router->put("/api/days/$date/steps", [DayController::class, 'saveSteps']);
$router->post("/api/days/$date/foods", [DayController::class, 'addFood']);
$router->delete("/api/foods/$id", [DayController::class, 'deleteFood']);
$router->get('/api/foods/suggestions', [FoodController::class, 'suggestions']);
$router->post("/api/days/$date/sets", [DayController::class, 'addSet']);
$router->delete("/api/sets/$id", [DayController::class, 'deleteSet']);

$router->put("/api/days/$date/tags", [DayController::class, 'saveTags']);
$router->put("/api/days/$date/note", [DayController::class, 'saveNote']);

$router->get('/api/tags', [TagController::class, 'index']);
$router->post('/api/tags', [TagController::class, 'create']);
$router->post("/api/tags/$id/restore", [TagController::class, 'restore']);
$router->delete("/api/tags/$id", [TagController::class, 'delete']);

$router->get('/api/exercises', [ExerciseController::class, 'index']);
$router->post('/api/exercises', [ExerciseController::class, 'create']);
$router->post("/api/exercises/$id/restore", [ExerciseController::class, 'restore']);
$router->delete("/api/exercises/$id", [ExerciseController::class, 'delete']);

$router->get('/api/progress', [ProgressController::class, 'index']);
$router->get('/api/calendar', [ProgressController::class, 'calendar']);

$router->get('/api/version', [VersionController::class, 'show']);
$router->get('/api/settings', [SettingsController::class, 'show']);
$router->put('/api/settings', [SettingsController::class, 'save']);

$router->post("/api/days/$date/analysis", [AnalysisController::class, 'analyze']);

try {
    Migrator::run();
    $router->dispatch(Request::fromGlobals());
} catch (HttpException $e) {
    Response::json(['error' => $e->getMessage()], $e->status);
} catch (PDOException $e) {
    error_log((string) $e);
    // 2002/2006: MySQL not (yet) reachable, e.g. right after starting the containers
    $starting = preg_match('/\[(2002|2006)\]/', $e->getMessage()) === 1;
    Response::json(
        ['error' => $starting ? 'Database is still starting. Wait a moment and reload.' : 'Internal server error'],
        $starting ? 503 : 500
    );
} catch (Throwable $e) {
    error_log((string) $e);
    Response::json(['error' => 'Internal server error'], 500);
}
