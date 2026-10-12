#!/usr/bin/env python3
"""Assemble the facts the two per-bill synopses are written from.

    python3 -I dossier.py <workdir> <ctxdir> <votes.xml> <member-surname-key> <out.json>
    python3 -I dossier.py vp-all ctx votes.xml Williams dossier.json

<workdir>  the parse.py work dir (divisions.json: every division with the names)
<ctxdir>   hansard-<session>.json (hansard.py), bills-<session>.json (bill_context.py),
           sponsor-<session>.json (sponsor_speech.py), intro-statements.json (intro_statement.py),
           roster.json (roster.py)
<votes.xml> the candidate's votes as build_xml.py wrote them

Writes one record per bill: the bill's own facts (sponsor, explanatory note, dates, Royal
Assent), the candidate's votes on it with the result and tally, how the candidate's caucus
voted on each division, and everything the candidate said about it in debate. Nothing is
inferred here; the synopsis author reads this and writes from it.
"""
import collections
import glob
import json
import os
import re
import sys
import xml.etree.ElementTree as ET

work, ctx, votes_xml, key, out = sys.argv[1:6]
divisions = json.load(open(os.path.join(work, "divisions.json")))
roster = json.load(open(os.path.join(ctx, "roster.json")))
elected = {m["key"] for m in roster if m["elected_as"] == "Conservative"}
party_of = {m["key"]: m["elected_as"] for m in roster}
bills = {}
speeches = {}
for p in glob.glob(os.path.join(ctx, "bills-*.json")):
    s = os.path.basename(p)[6:-5]
    bills[s] = json.load(open(p))
intro = {}
if os.path.exists(os.path.join(ctx, "intro-statements.json")):
    intro = json.load(open(os.path.join(ctx, "intro-statements.json")))
sponsors = {}
for p in glob.glob(os.path.join(ctx, "sponsor-*.json")):
    sponsors[os.path.basename(p)[8:-5]] = json.load(open(p))
for p in glob.glob(os.path.join(ctx, "hansard-*.json")):
    s = os.path.basename(p)[8:-5]
    speeches[s] = json.load(open(p))

by_date = collections.defaultdict(list)
for d in divisions:
    ys = set(d["yeas"]["names"]) if d.get("yeas") else set()
    ns = set(d["nays"]["names"]) if d.get("nays") else set()
    by_date[d["date"]].append((ys, ns))


def caucus(date: str, how: str, f, a):
    day = by_date.get(date, [])
    c = [x for x in day if len(x[0]) == f and len(x[1]) == a]
    if len(c) != 1:
        c = [x for x in day if key in (x[0] if how == "yea" else x[1])]
        if len(c) > 1:
            c = [x for x in c if len(x[0]) == f or len(x[1]) == a] or c
    if len(c) != 1:
        return None
    ys, ns = c[0]
    tally = lambda names: dict(collections.Counter(party_of.get(n, "?") for n in names))
    return {
        "caucus_yea": len(ys & elected), "caucus_nay": len(ns & elected),
        # who voted which way, by the party each member was elected under
        "yea_by_party": tally(ys), "nay_by_party": tally(ns),
    }


rec = collections.OrderedDict()
for v in ET.parse(votes_xml).getroot().iter("vote"):
    g = lambda t: (v.findtext(t) or "").strip() or None
    if not g("bill_key"):
        continue
    session, ref = g("bill_key").split(":", 1)
    b = rec.setdefault(g("bill_key"), {
        "bill_key": g("bill_key"), "bill_title": g("bill_title"), "session": session,
        "bill": bills.get(session, {}).get(ref),
        "sponsor_opening": sponsors.get(session, {}).get(ref),
        "intro_statement": intro.get(g("bill_key")),
        "speeches": speeches.get(session, {}).get(ref, {}).get("speeches", []),
        "votes": [],
    })
    f = int(g("tally_for")) if g("tally_for") else None
    a = int(g("tally_against")) if g("tally_against") else None
    b["votes"].append({
        "date": g("voted_on"), "stage": g("stage"), "title": g("title"), "vote": g("vote"), "outcome": g("outcome"),
        "for": f, "against": a, "description": g("description"), "source_url": g("source_url"),
        "caucus": caucus(g("voted_on"), g("vote"), f, a) if f is not None else None,
    })
json.dump(list(rec.values()), open(out, "w"), ensure_ascii=False, indent=1)
print(f"{len(rec)} bills; {sum(1 for r in rec.values() if r['speeches'])} with a speech by the member")
