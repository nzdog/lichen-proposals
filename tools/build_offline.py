#!/usr/bin/env python3
"""Build the offline single-file generator.

site/_tpl/*.html  +  tools/shell.html  ->  tools/newproposal.html

The generator is deliberately one self-contained file: it is opened from disk,
fills a template in the browser, and hands back an index.html to upload. Editing
a 40KB template inside a JSON string is miserable, so the templates live as real
HTML here and this script stitches them back together.

This is the fallback for when the host is down — it runs entirely in the
browser and hands back an index.html to upload by hand. The everyday path is
the web generator at /p/_new.php.

The encoding matches the original byte for byte: ASCII-escaped JSON, with "</"
escaped so a "</script>" inside a template cannot close the generator's own
script tag.
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).parent
 
ORDER = ["general", "lawyer"]  # dropdown order in shell.html


def main() -> None:
    templates = {
        name: (ROOT.parent / "site" / "_tpl" / f"{name}.html").read_text()
        for name in ORDER
    }
    blob = json.dumps(templates, ensure_ascii=True).replace("</", "<\\/")

    shell = (ROOT / "shell.html").read_text()
    if "__TEMPLATES__" not in shell:
        raise SystemExit("shell.html has no __TEMPLATES__ marker")

    out = ROOT / "newproposal.html"
    out.write_text(shell.replace("__TEMPLATES__", blob))
    print(f"{out.relative_to(ROOT)}  {out.stat().st_size:,} bytes")


if __name__ == "__main__":
    main()
