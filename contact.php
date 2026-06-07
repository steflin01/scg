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
const MAX_MESSAGE_URLS = 0;
const MIN_MESSAGE_LENGTH = 15;
const MAX_NON_LATIN_LETTER_RATIO = 0.20;

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

function request_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return (string)($_SERVER[$key] ?? '');
}

function contains_url(string $value): bool
{
    return preg_match('~(?:https?://|www\.|[a-z0-9.-]+\.[a-z]{2,})(?:\S*)~iu', $value) === 1;
}

function url_count(string $value): int
{
    preg_match_all('~(?:https?://|www\.|[a-z0-9.-]+\.[a-z]{2,})(?:\S*)~iu', $value, $matches);
    return count($matches[0] ?? []);
}

function has_spam_pattern(string $value): bool
{
    $patterns = [
        '~\[(?:url|link)=~i',
        '~</?a\b~i',
        '~\b(?:casino|viagra|cialis|crypto|bitcoin|forex|loan|porn|escort)\b~i',
        '~\b(?:backlink|guest\s*post|link\s*building|seo\s*services|increase\s*traffic)\b~i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value) === 1) {
            return true;
        }
    }

    return false;
}

function has_too_many_non_latin_letters(string $value): bool
{
    preg_match_all('~\p{L}~u', $value, $letterMatches);
    $letters = count($letterMatches[0] ?? []);
    if ($letters < 12) {
        return false;
    }

    preg_match_all('~(?![\p{Latin}\p{Common}\p{Inherited}])\p{L}~u', $value, $nonLatinMatches);
    $nonLatinLetters = count($nonLatinMatches[0] ?? []);

    return ($nonLatinLetters / $letters) > MAX_NON_LATIN_LETTER_RATIO;
}

function reject_suspicious_submission(): never
{
    respond(400, false, 'Bitte prüfe deine Eingaben und sende das Formular erneut.');
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

if (request_header('X-SCG-Form') !== 'contact') {
    reject_suspicious_submission();
}

$startedAt = (int)field('form_started_at');
$formToken = field('form_token');
if ($startedAt <= 0 || $formToken !== ('scg-contact-' . $startedAt)) {
    reject_suspicious_submission();
}

if ((time() - $startedAt) < MIN_SUBMIT_SECONDS) {
    reject_suspicious_submission();
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

if (mb_strlen($message) < MIN_MESSAGE_LENGTH) {
    respond(400, false, 'Bitte schreibe eine etwas ausführlichere Nachricht.');
}

if (mb_strlen($name) > 120 || mb_strlen($email) > 180 || mb_strlen($subject) > 180) {
    respond(400, false, 'Eine deiner Eingaben ist zu lang.');
}

if (mb_strlen($message) > 5000) {
    respond(400, false, 'Deine Nachricht ist zu lang.');
}

if (contains_url($name) || contains_url($subject) || url_count($message) > MAX_MESSAGE_URLS) {
    reject_suspicious_submission();
}

if (has_spam_pattern($subject . "\n" . $message)) {
    reject_suspicious_submission();
}

if (has_too_many_non_latin_letters($subject . "\n" . $message)) {
    reject_suspicious_submission();
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
