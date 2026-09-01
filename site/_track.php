<?php
/* Cookieless open-tracking. Logs a UTC timestamp, the proposal slug and the
   event only. No IP address, no user agent, no cookie, no third party.

   The first time each event happens for a proposal, it emails you. Three
   messages per proposal at most — opened, reached the offer, read to the end —
   so a person re-reading it does not fill your inbox. */

require __DIR__ . '/_lib.php';

$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$e  = preg_replace('/[^a-z]/',          '', $_GET['e']  ?? '');

http_response_code(204);
if ($id === '' || $e === '' || strlen($id) > 64) { exit; }

/* Nigel's own browsers carry this, set by _me.php. Checking a link should not
   look like the recipient reading it. */
if (($_COOKIE['lp_no_track'] ?? '') === '1') { exit; }

$dir  = __DIR__ . '/_log';
$file = $dir . '/hits.csv';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

$rows = is_file($file)
      ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
      : [];

$note = first_time_note($id, $e, $rows, __DIR__);

@file_put_contents($file, gmdate('Y-m-d H:i:s') . ",{$id},{$e}\n",
                   FILE_APPEND | LOCK_EX);

if ($note !== null) { send_note($note); }


/* Returns [subject, body] the first time this event fires for this proposal,
   or null. Returns null for a slug with no folder, so a stranger poking at
   _track.php with invented ids cannot generate mail. */
function first_time_note(string $id, string $e, array $rows, string $base): ?array
{
    $labels = [
        'open'  => 'opened the proposal',
        'price' => 'reached the offer',
        'end'   => 'read it to the end',
    ];
    if (!isset($labels[$e]) || !is_dir($base . '/' . $id)) { return null; }

    foreach ($rows as $row) {
        $p = explode(',', $row);
        if (count($p) >= 3 && $p[1] === $id && $p[2] === $e) { return null; }
    }

    $who = recipient_name($id, $base) ?: $id;

    /* Local time is what you think in; the log stays UTC. */
    try {
        $when = (new DateTime('now', new DateTimeZone('Pacific/Auckland')))
              ->format('D j M, g:ia');
    } catch (Throwable $ex) {
        $when = gmdate('D j M, H:i') . ' UTC';
    }

    $body = "$who {$labels[$e]}.\n\n$when\n\n"
          . "https://lichenprotocol.com/p/$id/\n";

    $key = (string)(proposal_config()['view_key'] ?? '');
    if ($key !== '') {
        $body .= "https://lichenprotocol.com/p/_view.php?k=" . rawurlencode($key) . "\n";
    }

    return ["$who — {$labels[$e]}", $body];
}

function recipient_name(string $id, string $base): string
{
    $sent = $base . '/_log/sent.csv';
    if (!is_file($sent)) { return ''; }
    foreach (file($sent, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $row) {
        $p = explode(',', $row);
        if (count($p) >= 4 && $p[1] === $id) { return trim($p[3]); }
    }
    return '';
}

function send_note(array $note): void
{
    $to = notify_address();
    if ($to === '') { mail_log('no address configured', $note[0]); return; }

    $ok = @mail($to, $note[0], $note[1],
                "From: $to\r\nContent-Type: text/plain; charset=UTF-8\r\n");

    mail_log($ok ? "accepted for $to" : "REFUSED by mail() for $to", $note[0]);
}
