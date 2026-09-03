# Lichen proposals

The proposal system behind `lichenprotocol.com/p`. Fill a form in the browser,
and a personalised page is written straight to the server with its own
unguessable URL. Push to `main` and the templates and PHP redeploy themselves.

## Daily use

| | |
|---|---|
| Write a proposal | `https://lichenprotocol.com/p/_new.php?k=<new_key>` |
| See what you have sent | `https://lichenprotocol.com/p/_view.php?k=<view_key>` |

Both keys live in `_config.php` on the server. Neither is in this repo.

Give it their email address as well and the built panel offers a **Write the
email** button: your own mail client opens, addressed to them, with the link in
the body and `archive_email` from `_config.php` in the BCC. Nothing is sent from
the server — you still write and send it yourself — but the archive copy happens
because you clicked the button rather than because you remembered.

Filling the form creates `p/<slug>/index.html` and gives you the link. The slug
is their name plus six random hex characters, so `James Whitmore` becomes
something like `james-whitmore-a4f19c` — theirs alone, and not guessable from
knowing someone else's.

## Editing a proposal

The three pages are real HTML in `site/_tpl/`. Edit, commit, push to `main`, and
the change is live in about a minute. It applies to proposals written *after*
that — pages already sent are static files and do not change under the reader.

| File | Page |
|---|---|
| `_tpl/general.html` | Field Exit Series |
| `_tpl/lawyer.html` | Before You Decide What's Next |

Placeholders `_new.php` fills: `__HEADLINE__`, `__TO__`, `__FROM__`,
`__EMAIL__`, `__DATE__`, `__SITUATION__`. All of them are
HTML-escaped. The deploy fails if a template uses a placeholder that
`_new.php` does not know how to fill, so a typo cannot ship as literal
`__SITUAION__` text on a client's page.

## Editing the copy in bulk

`tools/extract_copy.py` pulls every block of prose out of both templates into
JSON, recording the exact character range each one occupies. Claude publishes
that as a copy deck — all 248 blocks in page order, editable in the browser —
and `tools/apply_copy.py` splices the edited blocks back by range, leaving
everything nobody touched byte for byte as it was.

    python3 tools/extract_copy.py > copy.json
    # edit in the deck, then
    python3 tools/apply_copy.py edited.json

Ranges rather than search-and-replace because the short blocks repeat: "75 min"
appears four times in one template and "NZ$600" three. apply_copy.py refuses to
write if a block's recorded range no longer holds the text it was extracted
from — that means the template moved under the deck, and splicing blind would
corrupt it.

Blocks carrying a `__PLACEHOLDER__` are locked in the deck: they are filled per
proposal from the form. They are also the only blocks with inline markup, which
is why every editable block can be treated as plain text.

## How the deploy works, and why it is shaped this way

`public_html/p/` holds two kinds of thing at once:

- **System** — the PHP, the `.htaccess` files, `_tpl/`. All in this repo.
- **Runtime state** — every `<slug>/` folder, and `_log/`. None of it in this repo.

Most deploy tooling *mirrors*: anything on the server that is not in the repo
gets deleted. Pointed at `p/`, that would erase live client proposals and the
record of what was sent, and every link already sent would start 404ing.

So `.github/workflows/deploy.yml` PUTs an explicit list of files over WebDAV
and has no mirror, sync or DELETE step anywhere in it. It cannot remove a
proposal folder because it has no ability to remove anything. The cost is that
renaming or deleting a file here leaves the old one on the server — tidy those
by hand, rarely.

After uploading it checks the live site: `/p/`, `/p/_log/sent.csv` and
`/p/_tpl/` all return 403, both gated pages return a bare 404 without a key,
and the retired beacon address still answers 204 for pages built before
tracking was switched off. A red build means the deploy landed wrong.

## What is recorded, and what is not

`_new.php` records what was sent to whom in `_log/sent.csv`, and `_view.php`
shows it, with each proposal's section 01 read back from the page's own HTML
rather than from a stored copy — so there is nothing to keep in step, and a
page edited by hand afterwards shows its edit.

Nothing records whether a proposal was opened or read. Earlier versions did:
a beacon on load, on reaching the offer and on reaching the close, with an
email the first time each happened. It was switched off in September 2026.
The pages promise that nothing leaves the session, and a page that silently
reported how far someone read and when they came back was on the wrong side
of that. If you want to know whether someone has read it, ask them.

Pages built before then still carry the script, so `_track.php` remains as a
stub that answers 204 and does nothing. It can be deleted once every proposal
from before that date is gone.

Deleting a proposal's folder is how a proposal is withdrawn: it stops appearing
in `_view.php`. Nothing is destroyed — the row stays in `sent.csv`, a footnote
names what is hidden, and putting the folder back brings it straight back.

## First-time setup

1. **Create `_config.php` on the server**, in `public_html/p/`, from
   `site/_config.example.php`. Generate two different keys:
   ```
   openssl rand -base64 18 | tr '/+' '_-'
   ```
   This file is never deployed and never in git, so a push cannot leak a key and
   a deploy cannot overwrite it. Rotating a key is editing this one file.
   Until it exists, both `_new.php` and `_view.php` return 404 — they fail closed.

2. **Make a Web Disk account.** cPanel → Files → **Web Disk** → Add Web Disk
   Account. This plan has FTP disabled, so Web Disk (WebDAV over HTTPS on port
   2078) is the transport.

   | Field | Value |
   |---|---|
   | Username | `deploy` — becomes `deploy@lichenprotocol.com` |
   | Password | use the generator |
   | Directory | `public_html/p` |
   | Permissions | Read-Write |

   Restrict the Directory rather than leaving it at the home folder. If it ever
   leaks, the blast radius is one directory instead of the whole account.

3. **Add three repository secrets** under Settings → Secrets and variables →
   Actions:

   | Secret | Value |
   |---|---|
   | `WEBDISK_HOST` | `minnie.whsl206.com` — the hostname from your cPanel URL, not the domain |
   | `WEBDISK_USER` | `deploy@lichenprotocol.com` — the full string |
   | `WEBDISK_PASS` | the password you generated |

   Because the account is rooted at `public_html/p`, also add a repository
   **variable** (Variables tab, not Secrets) `WEBDISK_DIR` set to `.` — without
   it the files land in `p/public_html/p/`.

4. **Push to `main`.** The workflow checks PHP syntax and template
   placeholders, checks the connection, uploads, then verifies the live site.

## Offline fallback

`python3 tools/build_offline.py` writes `tools/newproposal.html`, the original single-file
generator — it runs entirely in the browser and hands back an `index.html` to upload
by hand. It needs no server and no network. Keep it for when the host is down;
the web generator is the everyday path.

## Layout

```
site/                  mirrors public_html/p/
  _new.php             the generator — writes <slug>/index.html
  _view.php            the private record of what was sent
  _track.php           retired beacon address; answers 204, records nothing
  _lib.php             key checking, fails closed
  _config.example.php  copy to _config.php ON THE SERVER only
  .htaccess            Options -Indexes, X-Robots-Tag noindex
  _log/.htaccess       Require all denied
  _tpl/.htaccess       Require all denied
  _tpl/*.html          the three proposal pages
.github/workflows/deploy.yml
tools/                 offline single-file generator
```
