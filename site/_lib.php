<?php
/* Shared by every page under /p/. Fails closed: if _config.php is missing or
   malformed, every gated page 404s rather than falling open. */

function proposal_config(): array {
    $path = __DIR__ . '/_config.php';
    if (!is_file($path)) { return []; }
    $cfg = @include $path;
    return is_array($cfg) ? $cfg : [];
}

/* Compares in constant time so the key cannot be recovered by timing repeated
   requests. Any failure renders the same bare 404 as a missing file. */
function require_key(string $name): void {
    $cfg      = proposal_config();
    $expected = (string)($cfg[$name] ?? '');
    $given    = (string)($_GET['k'] ?? '');
    $ok = $expected !== ''
       && !str_starts_with($expected, 'CHANGE-ME')
       && hash_equals($expected, $given);
    if (!$ok) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        exit('Not found');
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* notify_email falls back to archive_email when it is empty, not only when it
   is absent. ?? does not do this: _config.example.php ships notify_email as an
   empty string, so a config copied from it resolved to '' and send_note
   returned before mail() was ever called. */
function notify_address(): string
{
    $cfg = proposal_config();
    $to  = trim((string)($cfg['notify_email'] ?? ''));
    return $to !== '' ? $to : trim((string)($cfg['archive_email'] ?? ''));
}

/* mail() returning true means only that the local MTA took it, so this cannot
   prove delivery. It does separate "never attempted" and "the host refused"
   from "it left here and something downstream ate it" — which is the part that
   was invisible. _log/ is denied to the web by its own .htaccess. */
function mail_log(string $outcome, string $subject): void
{
    @file_put_contents(__DIR__ . '/_log/mail.log',
        gmdate('Y-m-d H:i:s') . " | {$outcome} | {$subject}\n",
        FILE_APPEND | LOCK_EX);
}

