<?php
/* Web proposal generator.  https://lichenprotocol.com/p/_new.php?k=<new_key>

   Fill the form, and this writes public_html/p/<slug>/index.html and hands back
   the link. Replaces the download-and-upload round trip.

   The slug is built here, never accepted from the browser, and is re-validated
   against a strict pattern before any directory is touched — so a crafted name
   cannot escape /p/ or collide with _log, _tpl or any other underscore file. */

require __DIR__ . '/_lib.php';
require_key('new_key');

const VERSIONS = [
    'general' => ['file' => 'general.html', 'headline' => 'The Lichen Protocol Field Exit'],
    'lawyer'  => ['file' => 'lawyer.html',  'headline' => "Before you decide what's next"],
];

/* Reduces a name to a safe directory stem. Anything outside [a-z0-9] becomes a
   hyphen, so "O'Brien & Co." lands as "o-brien-co". */
function slugify(string $s): string {
    $s = trim($s);
    /* Fold accented letters to ASCII first. iconv's //TRANSLIT drops them
       outright under the C locale that PHP runs in on this host, which turned
       "Ømar Cuk-Astvaldsson" into "mar-uk-stvaldsson". */
    $s = strtr($s, [
        'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE','Ç'=>'C',
        'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
        'Ð'=>'D','Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
        'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y','Þ'=>'TH','ß'=>'ss',
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae','ç'=>'c',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ð'=>'d','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','þ'=>'th','ÿ'=>'y',
        'Ć'=>'C','ć'=>'c','Č'=>'C','č'=>'c','Š'=>'S','š'=>'s','Ž'=>'Z','ž'=>'z',
        'Ł'=>'L','ł'=>'l','Ń'=>'N','ń'=>'n','Ś'=>'S','ś'=>'s','Ź'=>'Z','ź'=>'z',
        'Ż'=>'Z','ż'=>'z','Ā'=>'A','ā'=>'a','Ē'=>'E','ē'=>'e','Ī'=>'I','ī'=>'i',
        'Ō'=>'O','ō'=>'o','Ū'=>'U','ū'=>'u',
    ]);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return substr($s, 0, 28) ?: 'proposal';
}

function build_html(string $version, array $f, string $slug): string {
    $tpl = file_get_contents(__DIR__ . '/_tpl/' . VERSIONS[$version]['file']);
    if ($tpl === false) { throw new RuntimeException('Template missing: ' . $version); }

    $who = h($f['to']) . ($f['org'] !== '' ? ' &middot; ' . h($f['org']) : '');
    return strtr($tpl, [
        '__HEADLINE__'  => h($f['headline'] !== '' ? $f['headline'] : VERSIONS[$version]['headline']),
        '__TO__'        => $who,
        '__FROM__'      => h($f['from']),
        '__EMAIL__'     => h($f['email']),
        '__DATE__'      => h($f['date']),
        '__SITUATION__' => h($f['situation']),
        '__SLUG__'      => $slug,
    ]);
}

$field = fn(string $n): string => trim((string)($_POST[$n] ?? ''));
$error = '';
$made  = null;
$preview = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = (string)($_POST['version'] ?? '');
    $f = [
        'to'        => $field('to'),
        'org'       => $field('org'),
        'situation' => $field('situation'),
        'date'      => $field('date'),
        'from'      => $field('from'),
        'email'     => $field('email'),
        'headline'  => $field('headline'),
    ];

    if (!isset(VERSIONS[$version])) {
        $error = 'Pick a version.';
    } elseif ($f['to'] === '') {
        $error = 'Their name is needed — it also decides the folder name.';
    } elseif ($f['situation'] === '') {
        $error = 'What I heard is section 01 and the first thing they read. It cannot be empty.';
    } else {
        try {
            if (($_POST['action'] ?? '') === 'preview') {
                /* Rendered, never written. The beacon in a preview resolves to
                   /_track.php rather than /p/_track.php, so it 404s and no
                   event reaches the log. */
                $preview = build_html($version, $f, 'preview');
            } else {
                $slug = slugify($f['to']) . '-' . bin2hex(random_bytes(3));

                if (!preg_match('/^[a-z0-9][a-z0-9-]{0,27}-[0-9a-f]{6}$/', $slug)) {
                    throw new RuntimeException('Could not build a safe folder name from that name.');
                }
                $dir = __DIR__ . '/' . $slug;
                if (file_exists($dir)) {
                    throw new RuntimeException('That folder already exists. Try again.');
                }

                $html = build_html($version, $f, $slug);

                if (!@mkdir($dir, 0755)) {
                    throw new RuntimeException('Could not create ' . $slug . '/ — check permissions on /p.');
                }
                if (@file_put_contents($dir . '/index.html', $html) === false) {
                    @rmdir($dir);
                    throw new RuntimeException('Could not write index.html.');
                }

                /* A record of what was sent to whom. The hit log only ever
                   knows slugs, so without this there is no way to see a
                   proposal that was sent and never opened. */
                @file_put_contents(__DIR__ . '/_log/sent.csv',
                    gmdate('Y-m-d H:i:s') . ',' . $slug . ',' . $version . ','
                    . str_replace(["\n", ","], ' ', $f['to']) . ','
                    . str_replace(["\n", ","], ' ', $f['org']) . "\n",
                    FILE_APPEND | LOCK_EX);

                $made = ['slug' => $slug, 'url' => 'https://lichenprotocol.com/p/' . $slug . '/'];
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($preview !== '') { echo $preview; exit; }

$self = '?k=' . rawurlencode((string)($_GET['k'] ?? ''));
$v    = (string)($_POST['version'] ?? 'general');
$val  = fn(string $n, string $d = ''): string => h((string)($_POST[$n] ?? $d));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<title>New proposal — The Lichen Protocol</title>
<style>
:root{
  --bg:#fafaf9; --surface:#fff; --surface-2:#f5f5f4;
  --border:#e7e5e4; --border-strong:#d6d3d1;
  --ink:#1c1917; --ink-2:#292524; --muted:#78716c; --muted-2:#a8a29e;
  --danger:#b4342c;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font:17px/1.65 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
     background:var(--bg);color:var(--ink);padding:56px 24px 96px}
.w{max-width:680px;margin:0 auto}
h1{font-size:1.5rem;font-weight:600;letter-spacing:-.02em;margin-bottom:6px}
p.sub{color:var(--muted);font-size:.9375rem;margin-bottom:36px}
label{display:block;font-size:.6875rem;font-weight:600;text-transform:uppercase;
      letter-spacing:.08em;color:var(--muted);margin-bottom:7px}
.f{margin-bottom:22px}
input,textarea,select{width:100%;font:inherit;font-size:.9375rem;color:var(--ink);
  background:var(--surface);border:1px solid var(--border-strong);border-radius:4px;
  padding:10px 12px}
textarea{min-height:120px;resize:vertical;line-height:1.6}
input:focus,textarea:focus,select:focus{outline:2px solid var(--muted);outline-offset:1px}
.hint{font-size:.8125rem;color:var(--muted-2);margin-top:6px;line-height:1.5}
.row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:560px){.row{grid-template-columns:1fr}}
button{font:inherit;font-size:.9375rem;font-weight:600;cursor:pointer;border:0;
  background:var(--ink);color:var(--bg);padding:13px 24px;border-radius:4px;
  transition:transform .15s ease}
button:hover{transform:translateY(-1px)}
button.ghost{background:transparent;color:var(--ink);border:1px solid var(--border-strong)}
.actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:8px}
.out{margin-bottom:34px;padding:24px;background:var(--surface-2);
     border:1px solid var(--border);border-radius:4px}
.out h2{font-size:1.0625rem;font-weight:600;margin-bottom:10px;letter-spacing:-.01em}
.out p{font-size:.9375rem;color:var(--muted);margin-bottom:14px}
.err{border-color:var(--danger);color:var(--danger)}
.err h2{color:var(--danger)}
code{font-family:ui-monospace,monospace;font-size:.8125rem;background:var(--surface);
  border:1px solid var(--border);padding:4px 8px;border-radius:3px;color:var(--ink-2);
  word-break:break-all;user-select:all;display:inline-block}
</style>
</head>
<body>
<div class="w">
  <h1>New proposal</h1>
  <p class="sub">Fill this in and the page is written straight to the server. No download, no upload.</p>

<?php if ($error !== ''): ?>
  <div class="out err"><h2>Not built</h2><p style="color:inherit"><?= h($error) ?></p></div>
<?php endif; ?>

<?php if ($made): ?>
  <div class="out">
    <h2>Live</h2>
    <p>Written to <code><?= h($made['slug']) ?>/index.html</code> — nothing else to do. Send them:</p>
    <p><code id="u"><?= h($made['url']) ?></code></p>
    <div class="actions">
      <a href="<?= h($made['url']) ?>" target="_blank" rel="noopener"><button type="button" class="ghost">Open it</button></a>
      <button type="button" class="ghost" id="copy">Copy the link</button>
      <span id="copied" style="font-size:.8125rem;color:var(--muted);opacity:0;transition:opacity .2s">Copied</span>
    </div>
  </div>
<?php endif; ?>

  <form method="post" action="<?= h($self) ?>">
    <div class="f">
      <label for="version">Which version</label>
      <select id="version" name="version">
        <option value="general"<?= $v === 'general' ? ' selected' : '' ?>>General — "The Lichen Protocol Field Exit"</option>
        <option value="lawyer"<?= $v === 'lawyer' ? ' selected' : '' ?>>Lawyer — "Before you decide what's next"</option>
      </select>
    </div>

    <div class="f">
      <label for="headline">Headline</label>
      <input id="headline" name="headline" autocomplete="off" value="<?= $val('headline') ?>">
      <p class="hint">Changes the big line at the top. Leave it empty to use the version's own headline.</p>
    </div>

    <div class="row">
      <div class="f">
        <label for="to">Their name</label>
        <input id="to" name="to" placeholder="James Whitmore" autocomplete="off" value="<?= $val('to') ?>">
      </div>
      <div class="f">
        <label for="org">Firm or company <span style="text-transform:none;letter-spacing:0">(optional)</span></label>
        <input id="org" name="org" placeholder="Bell Gully" autocomplete="off" value="<?= $val('org') ?>">
      </div>
    </div>

    <div class="f">
      <label for="situation">What I heard</label>
      <textarea id="situation" name="situation" placeholder="Two or three sentences from your conversation, in their words. What they said they're done with. What they said they want, even if it was vague. The thing they came back to twice."><?= $val('situation') ?></textarea>
      <p class="hint">This is section 01 and the first thing they read. Worth more than everything else on this form.</p>
    </div>

    <div class="row">
      <div class="f">
        <label for="date">Date</label>
        <input id="date" name="date" autocomplete="off" value="<?= $val('date', date('j F Y')) ?>">
      </div>
      <div class="f">
        <label for="from">Your name</label>
        <input id="from" name="from" autocomplete="off" value="<?= $val('from', 'Nigel Corbett') ?>">
      </div>
    </div>

    <div class="f">
      <label for="email">Your email</label>
      <input id="email" name="email" autocomplete="off" value="<?= $val('email', 'nigel@lichenprotocol.com') ?>">
    </div>

    <div class="actions">
      <button type="submit" name="action" value="build">Build the proposal</button>
      <button type="submit" name="action" value="preview" formtarget="_blank" class="ghost">Preview first</button>
    </div>
  </form>
</div>
<script>
var c = document.getElementById('copy');
if (c) c.addEventListener('click', function(){
  var f = document.getElementById('copied');
  function done(){ f.style.opacity = 1; setTimeout(function(){ f.style.opacity = 0; }, 1800); }
  try { navigator.clipboard.writeText(document.getElementById('u').textContent).then(done, done); }
  catch(e){ done(); }
});
</script>
</body>
</html>
