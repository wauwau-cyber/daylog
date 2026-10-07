<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Settings;

final class VersionController
{
    private const CHECK_EVERY_SECONDS = 6 * 3600;

    /** GET /api/version: running version and, for release builds, whether a newer release exists. */
    public function show(Request $request): void
    {
        $current = (string) (getenv('APP_VERSION') ?: 'dev');
        $repo = (string) getenv('APP_REPO');

        $latest = null;
        if ($current !== 'dev' && preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            $latest = $this->latestRelease($repo);
        }

        Response::json([
            'version' => $current,
            'latest' => $latest['version'] ?? null,
            'release_url' => $latest['url'] ?? null,
            'update_available' => $latest !== null && self::isNewer($latest['version'], $current),
        ]);
    }

    /** Latest GitHub release, cached in settings so GitHub is asked at most every few hours. */
    private function latestRelease(string $repo): ?array
    {
        $cached = json_decode((string) Settings::get('update_check'), true);
        if (is_array($cached) && ($cached['repo'] ?? '') === $repo
            && time() - (int) ($cached['checked_at'] ?? 0) < self::CHECK_EVERY_SECONDS) {
            return $cached['release'];
        }

        $release = null;
        $api = rtrim((string) (getenv('GITHUB_API_URL') ?: 'https://api.github.com'), '/');
        $ch = curl_init("$api/repos/$repo/releases/latest");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: daylog-update-check'],
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw !== false && $status === 200) {
            $data = json_decode((string) $raw, true);
            if (isset($data['tag_name'])) {
                $release = ['version' => ltrim((string) $data['tag_name'], 'v'), 'url' => $data['html_url'] ?? null];
            }
        }

        // offline or rate-limited: remember the miss too, so the app doesn't wait on GitHub every time
        Settings::set('update_check', json_encode(['repo' => $repo, 'checked_at' => time(), 'release' => $release]));
        return $release;
    }

    private static function isNewer(string $latest, string $current): bool
    {
        return version_compare(ltrim($latest, 'v'), ltrim($current, 'v'), '>');
    }
}
