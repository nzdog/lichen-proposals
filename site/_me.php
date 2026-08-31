<?php
/* Mark this browser as mine, so my own opens stay out of the log.
   https://lichenprotocol.com/p/_me.php?k=<new_key>        stop counting me
   https://lichenprotocol.com/p/_me.php?k=<new_key>&off    count me again

   Every time I open a proposal to check it, or to show someone, that visit is
   indistinguishable from the recipient reading it — and repeat visits days
   apart are exactly the signal _view.php treats as strongest. This sets a
   cookie that _track.php honours, so the data stays about them.

   Per browser and per device: run it once on each. Recipients are unaffected. */

require __DIR__ . '/_lib.php';
require_key('new_key');

const COOKIE = 'lp_no_track';

$off = isset($_GET['off']);
setcookie(COOKIE, $off ? '' : '1', [
    'expires'  => $off ? time() - 3600 : time() + 10 * 365 * 24 * 3600,
    'path'     => '/p/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

$already = ($_COOKIE[COOKIE] ?? '') === '1';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>This browser</title>
<style>
 body{font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
      background:#fafaf9;color:#1c1917;margin:0;padding:64px 24px}
 .w{max-width:520px;margin:0 auto}
 h1{font-size:1.25rem;letter-spacing:-.02em;margin:0 0 10px}
 p{color:#57534e;font-size:.9375rem;margin:0 0 14px}
 .state{display:inline-block;font-size:.75rem;font-weight:600;text-transform:uppercase;
        letter-spacing:.07em;padding:4px 10px;border-radius:3px;margin-bottom:18px;
        background:#f5f5f4;color:#57534e}
 .state.on{background:#eef2ee;color:#4b6a50}
 a{color:#1c1917}
 code{font-family:ui-monospace,monospace;font-size:.8125rem;background:#f5f5f4;
      padding:2px 6px;border-radius:3px}
</style></head><body><div class="w">
<?php if ($off): ?>
  <span class="state">Counting you again</span>
  <h1>Your visits will be logged</h1>
  <p>This browser is back to being counted like anyone else. Opens, price and end
     events from here will appear in the activity log.</p>
  <p>To stop counting yourself again, drop the <code>&amp;off</code>.</p>
<?php else: ?>
  <span class="state on">Not counting you</span>
  <h1>This browser stays out of the log</h1>
  <p>Open as many proposals as you like from here — nothing you do will reach
     <code>hits.csv</code>. Recipients are unaffected.</p>
  <p>It is set per browser and per device, so run this once on each machine you
     check links from. Clearing your cookies undoes it.</p>
  <p>To be counted again, add <code>&amp;off</code> to this URL.</p>
<?php endif; ?>
</div></body></html>
