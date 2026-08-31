#!/usr/bin/env python3
"""Put edited copy back into the templates.

    python3 tools/apply_copy.py edited.json

Reads the JSON the copy deck produces and splices each changed block into its
template by character range, back to front so earlier offsets stay valid.
Everything nobody touched is left byte for byte as it was.

Refuses to write if a block's recorded range no longer holds the text it was
extracted from — that means the template moved under the deck, and splicing
blind would corrupt it. Re-extract and redo the edits instead.
"""
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).parent.parent


def main(path):
    deck = json.loads(pathlib.Path(path).read_text())
    total = 0

    for name, data in deck.items():
        tpl = ROOT / "site" / "_tpl" / f"{name}.html"
        src = tpl.read_text()

        stale = [b for b in data["blocks"] if src[b["start"]:b["end"]] != b["html"]]
        if stale:
            print(f"{name}: template has moved under the deck — "
                  f"{len(stale)} block(s) no longer match their recorded range.",
                  file=sys.stderr)
            for b in stale[:5]:
                print(f"    {b['id']} [{b['heading']}]", file=sys.stderr)
            print("Re-run extract_copy.py and redo the edits.", file=sys.stderr)
            return 1

        changed = [b for b in data["blocks"]
                   if b.get("edited") is not None and b["edited"] != b["html"]]
        for b in sorted(changed, key=lambda x: -x["start"]):
            src = src[:b["start"]] + b["edited"] + src[b["end"]:]

        if changed:
            tpl.write_text(src)
            total += len(changed)
            print(f"{name}: {len(changed)} block(s) updated")
            for b in changed:
                print(f"    {b['heading']}: {b['html'][:52]!r}")
                print(f"    {' ' * len(b['heading'])}  -> {b['edited'][:52]!r}")
        else:
            print(f"{name}: nothing changed")

    print(f"\n{total} block(s) updated in total."
          f"{'  Run build_offline.py if you use the offline generator.' if total else ''}")
    return 0


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("usage: apply_copy.py edited.json")
    sys.exit(main(sys.argv[1]))
