#!/usr/bin/env python3
"""Who sat for which party in the 43rd Parliament, as a roster the caucus check can use.

    python3 -I roster.py <out.json>

Reads Wikipedia's "43rd Parliament of British Columbia" members table (public page).
Each member gets `elected_as` (the party they were elected under) and `final` (where they
sit now, after any floor crossings or caucus changes), and `key`: the form the Votes and
Proceedings print their name in (surname, plus an initial where two members share one).

The caucus check uses `elected_as`, because a member who later left still voted with
that caucus until they left. Say so in the synopsis when a caucus has shrunk.
"""
import html
import json
import re
import sys
import urllib.request
from html.parser import HTMLParser

URL = "https://en.wikipedia.org/wiki/43rd_Parliament_of_British_Columbia"
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"


class Tables(HTMLParser):
    def __init__(self):
        super().__init__()
        self.tables, self.t, self.r, self.c = [], None, None, None

    def handle_starttag(self, tag, attrs):
        if tag == "table":
            self.t = []
        elif tag == "tr" and self.t is not None:
            self.r = []
        elif tag in ("td", "th") and self.r is not None:
            self.c = ""

    def handle_endtag(self, tag):
        if tag in ("td", "th") and self.c is not None and self.r is not None:
            self.r.append(re.sub(r"\s+", " ", html.unescape(self.c)).strip())
            self.c = None
        elif tag == "tr" and self.r is not None and self.t is not None:
            self.t.append(self.r)
            self.r = None
        elif tag == "table" and self.t is not None:
            self.tables.append(self.t)
            self.t = None

    def handle_data(self, data):
        if self.c is not None:
            self.c += data


def key_of(name: str) -> str:
    for s in ("Chandra Herbert", "Van Popta"):
        if name.endswith(s):
            return s
    parts = name.split()
    if parts[-1] in ("Anderson", "Neufeld"):
        return f"{parts[0][0]}. {parts[-1]}"
    return parts[-1]


def main() -> None:
    req = urllib.request.Request(URL, headers={"User-Agent": UA})
    page = urllib.request.urlopen(req, timeout=90).read().decode("utf-8", "ignore")
    p = Tables()
    p.feed(page)
    table = next(t for t in p.tables if t and "Electoral district" in t[0])
    clean = lambda s: re.sub(r"\[[^\]]*\]|[*†‡]", "", s).strip()
    members = []
    for r in table[1:]:
        if len(r) >= 4 and r[2] and not r[2].startswith("Vacant"):
            members.append({"name": clean(r[2]), "riding": clean(r[1]), "elected_as": clean(r[3]), "final": clean(r[3])})
        elif len(r) == 2 and members:  # a caucus change under the member's row
            members[-1]["final"] = clean(r[1])
    for m in members:
        m["key"] = key_of(m["name"])
    json.dump(members, open(sys.argv[1], "w"), ensure_ascii=False, indent=1)
    print(f"{len(members)} members written to {sys.argv[1]}")


if __name__ == "__main__":
    main()
