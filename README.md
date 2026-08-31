# Lichen proposals

The proposal system behind `lichenprotocol.com/p`. Fill a form in the browser,
and a personalised page is written straight to the server with its own
unguessable URL. Push to `main` and the templates and PHP redeploy themselves.

## Daily use

| | |
|---|---|
| Write a proposal | `https://lichenprotocol.com/p/_new.php?k=<new_key>` |
| See who has read one | `https://lichenprotocol.com/p/_view.php?k=<view_key>` |

Both keys live in `_config.php` on the server. Neither is in this repo.

Filling the form creates `p/<slug>/index.html` and gives you the link. The slug
is their name plus six random hex characters, so `James Whitmore` becomes
something like `james-whitmore-a4f19c` — theirs alone, and not guessable from
knowing someone else's.

## Editing a proposal

The two pages are real HTML in `site/_tpl/`. Edit, commit, push to `main`, and
the change is live in about a minute. It applies to proposals written *after*
that — pages already sent are static files and do not change under the reader.

| File | Page |
|---|---|
| `_tpl/general.html` | Field Exit Series |
| `_tpl/lawyer.html` | Before You Decide What's Next |

Placeholders `_new.php` fills: `__HEADLINE__`, `__TO__`, `__FROM__`,
`__EMAIL__`, `__DATE__`, `__SITUATION__`, `__SLUG__`. Everything except the
slug is HTML-escaped. The deploy fails if a template uses a placeholder that
`_new.php` does not know how to fill, so a typo cannot ship as literal
`__SITUAION__` text on a client's page.

## How the deploy works, and why it is shaped this way

`public_html/p/` holds two kinds of thing at once:

- **System** — the PHP, the `.htaccess` files, `_tpl/`. All in this repo.
- **Runtime state** — every `<slug>/` folder, and `_log/`. None of it in this repo.

Most deploy tooling *mirrors*: anything on the server that is not in the repo
gets deleted. Pointed at `p/`, that would erase live client proposals and the
entire tracking log, and every link already sent would start 404ing.

So `.github/workflows/deploy.yml` uploads an explicit list of files over FTPS
and has no mirror, sync or delete step anywhere in it. It cannot remove a
proposal folder because it has no ability to remove anything. The cost is that
renaming or deleting a file here leaves the old one on the server — tidy those
by hand, rarely.

After uploading it checks the live site: the beacon answers 204, `/p/` and
`/p/_log/hits.csv` and `/p/_tpl/` all return 403, and both gated pages return a
bare 404 without a key. A red build means the deploy landed wrong.

## Tracking

Each page beacons `../_track.php` on load, on reaching the price, and on
reaching the close — a UTC timestamp, the slug and the event. No IP, no user
agent, no cookie, no third party. `_new.php` separately records what was sent to
whom in `_log/sent.csv`, so `_view.php` can show a proposal that was sent and
never opened. That silence is the most useful thing in there.

Read the depth badge, not the clock. Elapsed time is a floor on attention: a tab
left open inflates it, and a page restored from bfcache can fire `end` before
`open`. Repeat visits days apart are the signal worth trusting.

## First-time setup

1. **Create `_config.php` on the server**, in `public_html/p/`, from
   `site/_config.example.php`. Generate two different keys:
   ```
   openssl rand -base64 18 | tr '/+' '_-'
   ```
   This file is never deployed and never in git, so a push cannot leak a key and
   a deploy cannot overwrite it. Rotating a key is editing this one file.
   Until it exists, both `_new.php` and `_view.php` return 404 — they fail closed.

2. **Add three repository secrets** under Settings → Secrets and variables →
   Actions: `FTP_HOST`, `FTP_USER`, `FTP_PASS`. Use the cPanel FTP account for
   the domain. If TLS fails, set `FTP_HOST` to the server's own hostname
   (the one in the cPanel URL) rather than `lichenprotocol.com`.

3. **Push to `main`.** The workflow checks PHP syntax and template
   placeholders, uploads, then verifies the live site.

## Offline fallback

`python3 tools/build_offline.py` writes `tools/newproposal.html`, the original single-file
generator — it runs entirely in the browser and hands back an `index.html` to upload
by hand. It needs no server and no network. Keep it for when the host is down;
the web generator is the everyday path.

## Layout

```
site/                  mirrors public_html/p/
  _new.php             the generator — writes <slug>/index.html
  _view.php            the private activity log
  _track.php           the beacon endpoint
  _lib.php             key checking, fails closed
  _config.example.php  copy to _config.php ON THE SERVER only
  .htaccess            Options -Indexes, X-Robots-Tag noindex
  _log/.htaccess       Require all denied
  _tpl/.htaccess       Require all denied
  _tpl/*.html          the two proposal pages
.github/workflows/deploy.yml
tools/                 offline single-file generator
```
