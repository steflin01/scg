<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const RECIPIENT_EMAIL = 'steflin@muenster.de';
const RECIPIENT_NAME = 'SC Gremmendorf Badminton';
const MAIL_SUBJECT_PREFIX = 'Kontaktanfrage SCG Badminton';
const MIN_SUBMIT_SECONDS = 4;
const RATE_LIMIT_WINDOW_SECONDS = 3600;
const RATE_LIMIT_MAX_SUBMISSIONS = 5;
const RATE_LIMIT_MIN_SECONDS = 45;

function respond(int $statusCode, bool $success, string $message): never
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function field(string $name): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return preg_replace('/[^\P{C}\r\n\t]/u', '', $value) ?? $value;
}

function clean_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function rate_limit_path(string $ip): string
{
    return sys_get_temp_dir() . '/scg-contact-' . hash('sha256', $ip) . '.json';
}

function enforce_rate_limit(string $ip): void
{
    $now = time();
    $path = rate_limit_path($ip);
    $attempts = [];

    if (is_file($path)) {
        $content = file_get_contents($path);
        $decoded = json_decode($content !== false ? $content : '[]', true);
        if (is_array($decoded)) {
            $attempts = array_filter($decoded, static function ($timestamp) use ($now) {
                return is_int($timestamp) && $timestamp >= ($now - RATE_LIMIT_WINDOW_SECONDS);
            });
        }
    }

    sort($attempts);
    $lastAttempt = end($attempts);
    if (is_int($lastAttempt) && ($now - $lastAttempt) < RATE_LIMIT_MIN_SECONDS) {
        respond(429, false, 'Bitte warte einen Moment, bevor du erneut sendest.');
    }

    if (count($attempts) >= RATE_LIMIT_MAX_SUBMISSIONS) {
        respond(429, false, 'Zu viele Nachrichten in kurzer Zeit. Bitte versuche es später erneut.');
    }

    $attempts[] = $now;
    file_put_contents($path, json_encode(array_values($attempts)), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Diese Anfrage ist nicht erlaubt.');
}

if (field('website') !== '') {
    respond(200, true, 'Danke! Deine Nachricht wurde gesendet.');
}

$startedAt = (int)field('form_started_at');
if ($startedAt > 0 && (time() - $startedAt) < MIN_SUBMIT_SECONDS) {
    respond(400, false, 'Bitte prüfe deine Eingaben und sende das Formular erneut.');
}

enforce_rate_limit(client_ip());

$name = field('name');
$email = field('email');
$subject = field('subject');
$message = field('message');

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    respond(400, false, 'Bitte fülle alle Felder aus.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'Bitte gib eine gültige E-Mail-Adresse ein.');
}

if (mb_strlen($name) > 120 || mb_strlen($email) > 180 || mb_strlen($subject) > 180) {
    respond(400, false, 'Eine deiner Eingaben ist zu lang.');
}

if (mb_strlen($message) > 5000) {
    respond(400, false, 'Deine Nachricht ist zu lang.');
}

$safeName = clean_header_value($name);
$safeEmail = clean_header_value($email);
$safeSubject = clean_header_value($subject);

$mailSubject = MAIL_SUBJECT_PREFIX . ': ' . $safeSubject;
$mailBody = implode("\n\n", [
    'Neue Kontaktanfrage über die Website des SC Gremmendorf Badminton',
    'Name: ' . $name,
    'E-Mail: ' . $email,
    'Betreff: ' . $subject,
    "Nachricht:\n" . $message,
]);

$headers = [
    'From: ' . RECIPIENT_NAME . ' <' . RECIPIENT_EMAIL . '>',
    'Reply-To: ' . $safeName . ' <' . $safeEmail . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = mail(RECIPIENT_EMAIL, $mailSubject, $mailBody, implode("\r\n", $headers));

if (!$sent) {
    respond(500, false, 'Die Nachricht konnte leider nicht gesendet werden.');
}

respond(200, true, 'Danke! Deine Nachricht wurde gesendet.');
