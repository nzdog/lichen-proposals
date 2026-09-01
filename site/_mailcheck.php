<?php
/* Why no notification arrived.
   https://lichenprotocol.com/p/_mailcheck.php?k=<new_key>          just report
   https://lichenprotocol.com/p/_mailcheck.php?k=<new_key>&send     also send one

   Every step of the notification path is silent on purpose — a beacon that
   throws is worse than one that misses a message — so when nothing arrives
   there is nothing to read. This says which step gave up.

   It will only ever mail the address in _config.php. There is deliberately no
   way to name a recipient in the URL: that would be an open relay behind a
   key that has already been read over someone's shoulder once. */

require __DIR__ . '/_lib.php';
require_key('new_key');

$cfg      = proposal_config();
$notify   = trim((string)($cfg['notify_email']  ?? ''));
$archive  = trim((string)($cfg['archive_email'] ?? ''));
$to       = notify_address();

$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$haveMail = function_exists('mail') && !in_array('mail', $disabled, true);

$sent = null;
if (isset($_GET['send']) && $to !== '' && $haveMail) {
    $sent = @mail($to, 'Field Exit — mail check',
        "This is _mailcheck.php.\n\nIf you are reading it, PHP mail() reaches "
      . "your inbox from this host and open notifications will too.\n",
        "From: $to\r\nContent-Type: text/plain; charset=UTF-8\r\n");
    mail_log($sent ? "accepted for $to" : "REFUSED by mail() for $to", 'mail check');
}

$log  = __DIR__ . '/_log/mail.log';
$tail = is_file($log)
      ? array_slice(file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -12)
      : [];

/* Reported in the order the notification path runs, so the first row that is
   not "ok" is the one that stopped it. */
$rows = [
    ['_config.php found',      $cfg !== [],   $cfg !== [] ? 'loaded' : 'missing or not returning an array'],
    ['notify_email',           true,          $notify !== '' ? $notify : 'empty'],
    ['archive_email',          true,          $archive !== '' ? $archive : 'empty'],
    ['address it would use',   $to !== '',    $to !== '' ? $to : 'none — send_note() gives up here'],
    ['mail() available',       $haveMail,     $haveMail ? 'yes' : 'disabled in php.ini'],
    ['sendmail_path',          true,          ((string)ini_get('sendmail_path')) ?: 'not set'],
];
if ($sent !== null) {
    $rows[] = ['test message', $sent, $sent ? "handed to the server for $to" : 'mail() returned false'];
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Mail check</title>
<style>
 body{font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
      background:#fafaf9;color:#1c1917;margin:0;padding:56px 24px}
 .w{max-width:640px;margin:0 auto}
 h1{font-size:1.25rem;letter-spacing:-.02em;margin:0 0 6px}
 .sub{color:#78716c;font-size:.875rem;margin:0 0 28px}
 table{border-collapse:collapse;width:100%;margin-bottom:28px}
 td{padding:9px 0;border-bottom:1px solid #e7e5e4;vertical-align:top;font-size:.9375rem}
 td.k{color:#78716c;width:42%;padding-right:16px}
 td.v{font-family:ui-monospace,monospace;font-size:.8125rem;word-break:break-word}
 .bad td{color:#8a3b3b}
 .bad td.k{color:#8a3b3b}
 h2{font-size:.75rem;text-transform:uppercase;letter-spacing:.07em;color:#78716c;margin:0 0 10px}
 pre{background:#f5f5f4;border-radius:3px;padding:14px;overflow-x:auto;
     font-size:.75rem;line-height:1.7;margin:0 0 22px}
 p{color:#57534e;font-size:.875rem;margin:0 0 12px}
 a{color:#1c1917}
</style></head><body><div class="w">
<h1>Mail check</h1>
<p class="sub">The notification path, in the order it runs.</p>

<table>
<?php foreach ($rows as [$k, $ok, $v]): ?>
  <tr class="<?= $ok ? '' : 'bad' ?>"><td class="k"><?= h($k) ?></td><td class="v"><?= h($v) ?></td></tr>
<?php endforeach; ?>
</table>

<?php if ($sent === null && $to !== '' && $haveMail): ?>
  <p><a href="?k=<?= rawurlencode((string)($_GET['k'] ?? '')) ?>&amp;send">Send a test message to <?= h($to) ?></a></p>
<?php endif; ?>

<?php if ($tail): ?>
  <h2>Recent attempts</h2>
  <pre><?= h(implode("\n", $tail)) ?></pre>
<?php else: ?>
  <h2>Recent attempts</h2>
  <p>Nothing logged yet. Notifications recorded from now on appear here, whether
     they were sent, refused, or skipped for want of an address.</p>
<?php endif; ?>

<p>A message accepted here can still be lost downstream. If mail() says yes and
   nothing arrives, check junk, then check cPanel &rarr; Email Routing: if this
   domain&rsquo;s MX is somewhere other than the hosting account, routing must be
   <em>Remote Mail Exchanger</em> or the server quietly delivers to a local
   mailbox nobody reads.</p>
</div></body></html>
