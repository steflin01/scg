<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

const TOURNAMENT_ID = 'D6F7A756-39F3-4CB4-8F27-0E30E0421F4A';
const BASE_URL = 'https://dbv.turnier.de';
const OUTPUT_FILE = __DIR__ . '/fixtures.json';
const CONFIG_FILE = __DIR__ . '/.update-fixtures-token.php';
const TEAMS = [
    '710' => 'SC Gremmendorf 1',
    '758' => 'SC Gremmendorf 2',
    '811' => 'SC Gremmendorf 3',
    '861' => 'SC Gremmendorf J1',
];

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit;
}

function require_token(): void
{
    if (!is_file(CONFIG_FILE)) {
        respond(500, ['success' => false, 'message' => 'Token-Konfiguration fehlt.']);
    }

    $config = require CONFIG_FILE;
    $expectedToken = (string)($config['token'] ?? '');
    $providedToken = (string)($_GET['token'] ?? '');

    if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        respond(403, ['success' => false, 'message' => 'Zugriff verweigert.']);
    }
}

function fetch_url(string $url, ?string $postFields = null): string
{
    $cookieFile = sys_get_temp_dir() . '/scg-dbv-cookies.txt';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/125 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: de-DE,de;q=0.9,en;q=0.8'],
    ]);

    if ($postFields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($body === false || $statusCode >= 400) {
        throw new RuntimeException('DBV-Abruf fehlgeschlagen: ' . ($error !== '' ? $error : 'HTTP ' . $statusCode));
    }

    return (string)$body;
}

function accept_cookie_wall(string $returnUrl): string
{
    $postFields = http_build_query([
        'ReturnUrl' => $returnUrl,
        'SettingsOpen' => 'false',
        'CookiePurposes' => '1',
    ]);

    return fetch_url(BASE_URL . '/cookiewall/Save', $postFields);
}

function fetch_team_matches(string $teamId): string
{
    $returnUrl = '/sport/teammatches.aspx?id=' . TOURNAMENT_ID . '&tid=' . $teamId;
    $page = fetch_url(BASE_URL . $returnUrl);

    if (str_contains($page, 'cookiewall/Save') || str_contains($page, 'message-page__modal')) {
        $page = accept_cookie_wall($returnUrl);
    }

    return $page;
}

function strip_cell(string $value): string
{
    $value = preg_replace('/<br\s*\/?>/i', ' ', $value) ?? $value;
    $value = preg_replace('/<[^>]+>/', ' ', $value) ?? $value;
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function extract_cells(string $rowHtml): array
{
    preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $matches);
    return array_map('strip_cell', $matches[1] ?? []);
}

function extract_match_url(string $rowHtml): string
{
    if (!preg_match('/href="([^"]*teammatch\.aspx\?[^"]+)"/i', $rowHtml, $match)) {
        return '';
    }

    $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (str_starts_with($url, 'http')) {
        return $url;
    }

    return BASE_URL . '/sport/' . ltrim($url, './');
}

function parse_german_datetime(string $value): ?DateTimeImmutable
{
    if (!preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/', $value, $match)) {
        return null;
    }

    return DateTimeImmutable::createFromFormat(
        '!d.m.Y H:i',
        "{$match[1]}.{$match[2]}.{$match[3]} {$match[4]}:{$match[5]}",
        new DateTimeZone('Europe/Berlin')
    ) ?: null;
}

function parse_matches(string $page): array
{
    if (!preg_match('/<table class="[^"]*\bmatches\b[^"]*">(.*?)<\/table>/is', $page, $tableMatch)) {
        return [];
    }

    preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableMatch[1], $rows);
    $matches = [];

    foreach ($rows[1] ?? [] as $rowHtml) {
        $cells = extract_cells($rowHtml);
        if (count($cells) < 12 || !preg_match('/\d{2}\.\d{2}\.\d{4}/', $cells[1])) {
            continue;
        }

        $plannedAt = parse_german_datetime($cells[1]);
        if (!$plannedAt) {
            continue;
        }

        $matches[] = [
            'datetime' => $plannedAt,
            'league' => $cells[2],
            'home' => $cells[6],
            'away' => $cells[8],
            'result' => $cells[9],
            'location' => $cells[11],
            'url' => extract_match_url($rowHtml),
        ];
    }

    return $matches;
}

function next_match(array $matches, DateTimeImmutable $now): ?array
{
    $futureMatches = array_values(array_filter($matches, static function (array $match) use ($now): bool {
        return $match['datetime'] >= $now;
    }));

    usort($futureMatches, static function (array $a, array $b): int {
        return $a['datetime'] <=> $b['datetime'];
    });

    return $futureMatches[0] ?? null;
}

require_token();

$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
$teams = [];
$errors = [];

foreach (TEAMS as $teamId => $teamName) {
    $teamId = (string)$teamId;
    try {
        $match = next_match(parse_matches(fetch_team_matches($teamId)), $now);
        if (!$match) {
            continue;
        }

        $teams[$teamId] = [
            'teamName' => $teamName,
            'datetime' => $match['datetime']->format(DateTimeInterface::ATOM),
            'home' => $match['home'],
            'away' => $match['away'],
            'location' => $match['location'],
            'url' => $match['url'],
        ];
    } catch (Throwable $exception) {
        $errors[$teamId] = $exception->getMessage();
    }
}

$payload = [
    'updatedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    'source' => BASE_URL,
    'teams' => $teams,
];

if ($errors !== []) {
    $payload['errors'] = $errors;
}

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false || file_put_contents(OUTPUT_FILE, $json . "\n", LOCK_EX) === false) {
    respond(500, ['success' => false, 'message' => 'fixtures.json konnte nicht geschrieben werden.']);
}

respond($errors === [] ? 200 : 207, [
    'success' => $errors === [],
    'message' => 'fixtures.json wurde aktualisiert.',
    'teams' => count($teams),
    'errors' => $errors,
]);
