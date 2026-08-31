<?php
/* Cookieless open-tracking. Logs a UTC timestamp, the proposal slug and the
   event only. No IP address, no user agent, no cookie, no third party. */
$id = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['id'] ?? '');
$e  = preg_replace('/[^a-z]/',          '', $_GET['e']  ?? '');

http_response_code(204);
if ($id === '' || $e === '' || strlen($id) > 64) { exit; }

/* Nigel's own browsers carry this, set by _me.php. Checking a link should not
   look like the recipient reading it. */
if (($_COOKIE['lp_no_track'] ?? '') === '1') { exit; }

$dir = __DIR__ . '/_log';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
@file_put_contents($dir . '/hits.csv',
    gmdate('Y-m-d H:i:s') . ",{$id},{$e}\n",
    FILE_APPEND | LOCK_EX);
