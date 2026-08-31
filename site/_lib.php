<?php
/* Shared by _new.php and _view.php. Fails closed: if _config.php is missing or
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
