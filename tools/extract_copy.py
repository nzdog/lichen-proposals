#!/usr/bin/env python3
"""Pull every block of copy out of the proposal templates.

    python3 tools/extract_copy.py > copy.json

Each block records the exact character range its text occupies in the
template, so apply_copy.py can splice an edit back in without reformatting
anything nobody touched. Text alone would not do: "75 min" and "NZ$600"
appear several times each.

Blocks carrying a __PLACEHOLDER__ are marked locked — they are filled per
proposal from the form, so there is nothing to edit here. They are also the
only blocks with inline markup, which is why the deck can treat every
editable block as plain text.
"""
import html.parser
import json
import pathlib
import re
import sys

LEAF = {"p", "h1", "h2", "h3", "li", "dt", "dd", "a"}

# Span classes that carry copy. "n" is a number, "sep" a glyph, "copied" a
# transient toast — none are text anyone would edit here.
SPAN_CLASSES = {"label", "label-lg", "t", "d", "dur", "big", "cap",
                "alt", "addr", "sig", "ref", "mono", "v"}

TEMPLATES = [("general", "Field Exit Series"),
             ("lawyer", "Before You Decide What's Next")]


class Extractor(html.parser.HTMLParser):
    """Walks the whole file so offsets are absolute. Script and style bodies
    are CDATA to the parser, so nothing inside them is mistaken for markup."""

    def __init__(self, src):
        super().__init__(convert_charrefs=False)
        self.src = src
        off, self.offsets = 0, []
        for line in src.splitlines(keepends=True):
            self.offsets.append(off)
            off += len(line)
        self.stack = []
        self.blocks = []
        self.section = None
        self.in_sec_head = 0
        self.zone = "Header"

    def pos(self):
        line, col = self.getpos()
        return self.offsets[line - 1] + col

    def handle_starttag(self, tag, attrs):
        classes = dict(attrs).get("class", "").split()

        if tag == "section":
            self.section = {"n": None, "heading": None}
        # The close and footer sit outside <section>; without clearing the
        # section they would inherit section 08's heading.
        if tag == "div" and "close" in classes:
            self.zone, self.section = "Close", None
        if tag == "footer":
            self.zone, self.section = "Footer", None
        if tag == "div" and "sec-head" in classes:
            self.in_sec_head += 1

        # span.n is the section number in a sec-head and the week number in
        # section 04's timeline. Only the first names a section.
        if tag == "span" and "n" in classes and self.in_sec_head:
            self.stack.append(("secnum", self.pos() + len(self.get_starttag_text())))
            return

        wanted = tag in LEAF or (tag == "span" and SPAN_CLASSES & set(classes))
        if wanted and not any(f[0] == "leaf" for f in self.stack):
            self.stack.append(("leaf", self.pos() + len(self.get_starttag_text()),
                               tag, " ".join(classes)))
        else:
            self.stack.append(("box", tag))

    def handle_endtag(self, tag):
        if not self.stack:
            return
        frame = self.stack.pop()

        if frame[0] == "secnum":
            if self.section is not None:
                self.section["n"] = self.src[frame[1]:self.pos()].strip()
            return

        if frame[0] == "leaf":
            start, end = frame[1], self.pos()
            inner = self.src[start:end]
            text = re.sub(r"<[^>]+>", "", inner).strip()
            if not text:
                return
            if frame[2] == "h2" and self.section and self.section["heading"] is None:
                self.section["heading"] = text
            self.blocks.append({
                "id": f"b{len(self.blocks):03d}",
                "tag": frame[2],
                "cls": frame[3],
                "section": (self.section or {}).get("n"),
                "heading": (self.section or {}).get("heading") or self.zone,
                "html": inner,
                "start": start,
                "end": end,
                "locked": bool(re.search(r"__[A-Z_]+__", text)),
            })
            return

        if frame[1] == "div" and self.in_sec_head:
            self.in_sec_head -= 1


def extract(name):
    src = (pathlib.Path(__file__).parent.parent / "site" / "_tpl" / f"{name}.html").read_text()
    p = Extractor(src)
    p.feed(src)
    # <head> contributes nothing editable; drop anything before <body>.
    body_at = src.index("<body>")
    return [b for b in p.blocks if b["start"] > body_at]


if __name__ == "__main__":
    out = {}
    for name, title in TEMPLATES:
        blocks = extract(name)
        out[name] = {"title": title, "blocks": blocks}
        print(f"{name}: {len(blocks)} blocks, "
              f"{sum(1 for b in blocks if b['locked'])} locked", file=sys.stderr)
    json.dump(out, sys.stdout, indent=1, ensure_ascii=False)
