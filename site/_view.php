<?php
/* Private activity log.  https://lichenprotocol.com/p/_view.php?k=<view_key>

   Reads _log/hits.csv for what people did, and _log/sent.csv for what was sent.
   Merging the two is the point: a proposal that was sent and never opened is
   invisible in the hit log, and that silence is worth seeing. */

require __DIR__ . '/_lib.php';
require_key('view_key');

$hits = [];
$file = __DIR__ . '/_log/hits.csv';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
        $p = explode(',', trim($row));
        if (count($p) < 3) { continue; }
        $hits[$p[1]][] = ['t' => $p[0], 'e' => $p[2]];
    }
}

/* Everything ever sent, newest first, whether or not it has been opened. */
$sent = [];
$file = __DIR__ . '/_log/sent.csv';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
        $p = explode(',', trim($row));
        if (count($p) < 4) { continue; }
        $sent[$p[1]] = ['t' => $p[0], 'version' => $p[2], 'to' => $p[3],
                        'org' => $p[4] ?? '', 'email' => $p[5] ?? ''];
    }
}

/* Proposals created before sent.csv existed still have a folder on disk. */
foreach (glob(__DIR__ . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $slug = basename($dir);
    if ($slug[0] === '_' || isset($sent[$slug])) { continue; }
    $sent[$slug] = ['t' => gmdate('Y-m-d H:i:s', (int)filemtime($dir)),
                    'version' => '', 'to' => '', 'org' => '', 'email' => ''];
}

foreach (array_keys($hits) as $slug) {
    if (!isset($sent[$slug])) {
        $sent[$slug] = ['t' => '', 'version' => '', 'to' => '', 'org' => '', 'email' => ''];
    }
}
/* Deleting a proposal's folder is how a proposal is withdrawn: one place to do
   it rather than two. The rows stay in sent.csv and hits.csv — nothing is
   destroyed here — they are simply not shown. */
$removed = [];
foreach (array_keys($sent) as $slug) {
    if (!is_file(__DIR__ . '/' . $slug . '/index.html')) {
        $removed[] = $slug;
        unset($sent[$slug]);
    }
}
sort($removed);

uasort($sent, fn($a, $b) => strcmp($b['t'], $a['t']));

$label = ['open' => 'Opened', 'price' => 'Reached the price', 'end' => 'Read to the end'];

/* What was actually sent. The proposal itself is the record — reading it back
   means there is nothing to keep in step, and an edit made by hand afterwards
   shows up here too. Returns [] when the folder has been deleted. */
function proposal_content(string $slug): array {
    $file = __DIR__ . '/' . $slug . '/index.html';
    if (!is_file($file)) { return []; }
    $s = (string)file_get_contents($file);

    $grab = static function (string $pattern) use ($s): string {
        if (!preg_match($pattern, $s, $m)) { return ''; }
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    };

    return [
        'template'  => $grab('~<title>(.*?)</title>~s'),
        'headline'  => $grab('~<h1[^>]*>(.*?)</h1>~s'),
        'to'        => $grab('~id="mFor"[^>]*>(.*?)</span>~s'),
        'dated'     => $grab('~id="mDate"[^>]*>(.*?)</span>~s'),
        'situation' => $grab('~id="situation"[^>]*>(.*?)</p>~s'),
        'built'     => gmdate('Y-m-d H:i', (int)filemtime($file)),
    ];
}

/* Depth reached across every visit, and how many separate visits there were.
   A gap of more than thirty minutes counts as a new visit. */
function summarise(array $events): array {
    if (!$events) { return ['depth' => 'Not opened', 'visits' => 0]; }
    usort($events, fn($a, $b) => strcmp($a['t'], $b['t']));
    $kinds  = array_column($events, 'e');
    $depth  = in_array('end', $kinds, true)   ? 'Read to the end'
            : (in_array('price', $kinds, true) ? 'Reached the price' : 'Opened only');
    $visits = 1;
    $prev   = strtotime($events[0]['t']);
    foreach ($events as $ev) {
        $t = strtotime($ev['t']);
        if ($t - $prev > 1800) { $visits++; }
        $prev = $t;
    }
    return ['depth' => $depth, 'visits' => $visits];
}
?><!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Proposal activity</title>
<style>
 body{font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
      background:#fafaf9;color:#1c1917;margin:0;padding:48px 24px}
 .w{max-width:760px;margin:0 auto}
 h1{font-size:1.5rem;letter-spacing:-.02em;margin:0 0 8px}
 p.sub{color:#78716c;margin:0 0 40px;font-size:.9375rem}
 h2{font-size:1.0625rem;margin:0 0 2px;letter-spacing:-.01em}
 .card{margin:0 0 28px;padding:20px 22px;background:#fff;border:1px solid #e7e5e4;border-radius:4px}
 .meta{font-size:.8125rem;color:#a8a29e;margin-bottom:12px}
 .depth{display:inline-block;font-size:.75rem;font-weight:600;text-transform:uppercase;
        letter-spacing:.06em;padding:3px 9px;border-radius:3px;background:#f5f5f4;color:#57534e}
 .depth.cold{background:#faf0ef;color:#b4342c}
 table{border-collapse:collapse;width:100%;font-size:.9375rem;margin-top:14px}
 td{padding:8px 0;border-top:1px solid #f5f5f4;color:#57534e}
 td.t{font-family:ui-monospace,monospace;font-size:.8125rem;color:#a8a29e;white-space:nowrap;width:12em}
 .empty{color:#a8a29e;font-size:.9375rem;margin-top:12px}
 .sent{margin-top:14px;border-top:1px solid #f5f5f4;padding-top:10px}
 .sent summary{font-size:.8125rem;color:#78716c;cursor:pointer;list-style:none;
               display:inline-flex;align-items:center;gap:6px}
 .sent summary::-webkit-details-marker{display:none}
 .sent summary::before{content:'▸';font-size:.6875rem;color:#a8a29e}
 .sent[open] summary::before{content:'▾'}
 .sent summary:hover{color:#1c1917}
 .sent-in{margin-top:12px;padding:14px 16px;background:#fafaf9;
          border:1px solid #f0eeec;border-radius:4px}
 .sent-meta{font-size:.75rem;color:#a8a29e;font-family:ui-monospace,monospace;
            margin-bottom:10px}
 .sent-meta a{color:#78716c}
 .sent-in h3{font-size:1rem;letter-spacing:-.01em;margin:0 0 8px}
 .sent-in p{font-size:.9375rem;color:#57534e;line-height:1.6;margin:0;
            max-width:64ch;white-space:pre-wrap}
 .gone{margin-top:8px;font-size:.8125rem;color:#a8a29e}
 .gone summary{cursor:pointer}
 .gone p{margin:8px 0 0;font-family:ui-monospace,monospace;font-size:.75rem;
         word-break:break-all}
 .gone p.why{font-family:inherit;font-size:.8125rem;max-width:60ch}
</style></head><body><div class="w">
<h1>Proposal activity</h1>
<p class="sub">Times are UTC. New Zealand is 12 hours ahead in winter, 13 in daylight saving.
Elapsed time is a floor on attention, not a measure of it — a tab left open inflates it.</p>

<?php if (!$sent): ?><p class="empty">Nothing sent yet.</p><?php endif; ?>

<?php foreach ($sent as $slug => $info):
    $events = $hits[$slug] ?? [];
    $s = summarise($events); ?>
  <div class="card">
    <h2><?= h($info['to'] !== '' ? $info['to'] : $slug) ?><?= $info['org'] !== '' ? ' <span style="color:#a8a29e;font-weight:400">· ' . h($info['org']) . '</span>' : '' ?></h2>
    <div class="meta">
      <?= h($slug) ?><?= $info['version'] !== '' ? ' · ' . h($info['version']) : '' ?><?= $info['t'] !== '' ? ' · sent ' . h(substr($info['t'], 0, 10)) : '' ?><?= ($info['email'] ?? '') !== '' ? ' · ' . h($info['email']) : '' ?>
    </div>
    <span class="depth<?= $s['visits'] === 0 ? ' cold' : '' ?>"><?= h($s['depth']) ?></span>
    <?php $c = proposal_content($slug); if ($c): ?>
      <details class="sent">
        <summary>What you sent</summary>
        <div class="sent-in">
          <div class="sent-meta">
            <?= h($c['template']) ?> · dated <?= h($c['dated']) ?> ·
            built <?= h($c['built']) ?> UTC ·
            <a href="<?= h($slug) ?>/" target="_blank" rel="noopener">open the page</a>
          </div>
          <h3><?= h($c['headline']) ?></h3>
          <p><?= h($c['situation']) ?></p>
        </div>
      </details>
    <?php endif; ?>

    <?php if ($s['visits'] > 0): ?>
      <span class="depth"><?= $s['visits'] ?> visit<?= $s['visits'] === 1 ? '' : 's' ?></span>
      <table><?php usort($events, fn($a, $b) => strcmp($b['t'], $a['t']));
        foreach ($events as $ev): ?>
        <tr><td class="t"><?= h($ev['t']) ?></td>
            <td><?= h($label[$ev['e']] ?? $ev['e']) ?></td></tr>
      <?php endforeach; ?></table>
    <?php else: ?>
      <p class="empty">Sent, but nothing recorded. They have not opened it.</p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($removed): ?>
  <details class="gone">
    <summary><?= count($removed) ?> proposal<?= count($removed) === 1 ? '' : 's' ?>
      whose page has been deleted <?= count($removed) === 1 ? 'is' : 'are' ?> not shown</summary>
    <p><?= h(implode(', ', $removed)) ?></p>
    <p class="why">Their rows are still in the log; only the page is gone.
       Put the folder back and the proposal reappears here.</p>
  </details>
<?php endif; ?>
</div></body></html>
