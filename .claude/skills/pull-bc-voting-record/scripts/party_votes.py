#!/usr/bin/env python3
"""How each party voted on the recorded divisions, for party-level fact-checks.

    python3 -I party_votes.py <work-dir> --search "carbon tax|Bill \\(No\\. 8\\)"      # divisions whose wording matches
    python3 -I party_votes.py <work-dir> --date 2025-03-31                            # every division that day
    python3 -I party_votes.py <work-dir> --summary                                     # each party's overall pattern

<work-dir> holds divisions.json (parse.py) and ctx/roster.json (roster.py), for every sitting of the
Parliament: run fetch.py and parse.py on it with no --days, which reads from the cache. Parties are
each member's party as elected (`elected_as` in the roster), so the Conservatives include MLAs who
later left for OneBC, CentreBC or sat as independents; say so when a check rests on a vote by
members of a party formed from them.

Each line: date, the question as the Votes and Proceedings word it, the tally, and which party's
members voted yea and nay. Only recorded divisions have names; voice votes are not in this record.
"""
import collections
import json
import os
import re
import sys

args = sys.argv[1:]
if not args:
    sys.exit(__doc__)
work = args[0]
divisions = json.load(open(os.path.join(work, "divisions.json")))
roster = json.load(open(os.path.join(work, "ctx", "roster.json")))
party_of = {m["key"]: m["elected_as"] for m in roster}
SHORT = {"Conservative": "Con", "NDP": "NDP", "Green": "Green"}


def wording(d):
    ctx = [re.sub(r"\s+", " ", l).strip() for l in d["context"] if l.strip()]
    text = " ".join(ctx[-9:])
    return re.sub(r"\[Division.*", "", text)[:420]


def split(names):
    c = collections.Counter(party_of.get(n, "?") for n in names)
    return ", ".join(f"{SHORT.get(p, p)} {n}" for p, n in sorted(c.items(), key=lambda x: -x[1])) or "none"


def line(d):
    ys = d["yeas"]["names"] if d.get("yeas") else []
    ns = d["nays"]["names"] if d.get("nays") else []
    return (
        f"{d['date']} {d['key']} | {wording(d)}\n"
        f"    tally {len(ys)}-{len(ns)} | YEA: {split(ys)} | NAY: {split(ns)}"
    )


def selected():
    if "--search" in args:
        pat = re.compile(args[args.index("--search") + 1], re.I)
        return [d for d in divisions if pat.search(" ".join(d["context"]))]
    if "--date" in args:
        day = args[args.index("--date") + 1]
        return [d for d in divisions if d["date"] == day]
    return None


if "--summary" in args:
    stats = collections.defaultdict(lambda: collections.Counter())
    for d in divisions:
        ys = d["yeas"]["names"] if d.get("yeas") else []
        ns = d["nays"]["names"] if d.get("nays") else []
        for p in {party_of.get(n, "?") for n in ys + ns}:
            y = sum(1 for n in ys if party_of.get(n) == p)
            n_ = sum(1 for n in ns if party_of.get(n) == p)
            stats[p]["divisions"] += 1
            stats[p]["unanimous within party"] += (y == 0 or n_ == 0)
            stats[p]["split"] += (y > 0 and n_ > 0)
    for p, c in sorted(stats.items()):
        print(f"{p}: in {c['divisions']} of {len(divisions)} divisions; voted as a block in {c['unanimous within party']}, split in {c['split']}")
    sys.exit()

rows = selected()
if rows is None:
    sys.exit(__doc__)
print(f"{len(rows)} of {len(divisions)} divisions")
for d in rows:
    print(line(d))
