#!/usr/bin/env python3
"""Turn the written per-bill synopses into an import file.

    python3 -I build_notes_xml.py <dossier.json> <synopses.json> "<Candidate name>" <out.xml>

<synopses.json> maps bill_key -> {purpose, vote_analysis, basis}; basis is one of
spoke | caucus | record (see SKILL.md, "Per-bill synopses"). Sources are not written by
hand: they are assembled from the dossier -- the bill text, the Hansard issues the
synopsis drew on (the sponsor's introduction, the member's own speeches) and the Votes
and Proceedings the divisions came from -- so every note links to what it rests on.
Refuses to write a note for a bill that is not in the dossier or has no basis.
"""
import json
import re
import sys
from xml.sax.saxutils import escape as e

dossier_path, syn_path, NAME, OUT = sys.argv[1:5]
dossier = {r["bill_key"]: r for r in json.load(open(dossier_path))}
syn = json.load(open(syn_path))

BASES = {"spoke", "caucus", "record"}
problems = [f"{k}: not in the dossier" for k in syn if k not in dossier]
problems += [f"{k}: basis must be one of {sorted(BASES)}" for k, v in syn.items() if v.get("basis") not in BASES]
problems += [f"{k}: needs a purpose and a vote_analysis" for k, v in syn.items() if not v.get("purpose") or not v.get("vote_analysis")]
problems += [f"{k}: has no synopsis" for k in dossier if k not in syn]
if problems:
    sys.exit("\n".join(problems))


def hansard_label(prefix: str, url: str, date: str) -> str:
    m = re.search(r"/(\d{4})(\d{2})(\d{2})", url)
    when = f"{m.group(1)}-{m.group(2)}-{m.group(3)}" if m else (date or "")
    return f"Hansard {when}: {prefix}".strip()


out = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    f"<!-- Per-bill synopses for {e(NAME)}'s voting record: what each bill is for, and why they voted as they did and how the question ended. Written from the Legislature's own record (bill text, Hansard, Votes and Proceedings); each note says what its 'why' rests on. -->",
    '<election id="bc-provincial-salmon-arm-shuswap">',
    "  <candidates>",
    "    <candidate>",
    f"      <name>{e(NAME)}</name>",
    "      <bill_notes>",
]
for key, r in dossier.items():
    n = syn[key]
    sources, seen = [], set()

    def add(label, url):
        if url and url not in seen:
            seen.add(url)
            sources.append((label, url))

    if r.get("bill"):
        add("Bill text (first reading)", r["bill"].get("text_url"))
    if r.get("sponsor_opening"):
        add(hansard_label("sponsor's introduction", r["sponsor_opening"]["url"], r["sponsor_opening"]["date"]), r["sponsor_opening"]["url"])
    if r.get("intro_statement"):
        add(hansard_label("introduction of the bill", r["intro_statement"]["url"], r["intro_statement"]["date"]), r["intro_statement"]["url"])
    for s in r.get("speeches", []):
        add(hansard_label("their speech", s["url"], s.get("date")), s["url"])
    for v in r["votes"]:
        add(f"Votes and Proceedings {v['date']}", v["source_url"])
        if len(sources) >= 8:
            break
    out += [
        "        <bill_note>",
        f"          <bill_key>{e(key)}</bill_key>",
        f"          <purpose>{e(n['purpose'])}</purpose>",
        f"          <vote_analysis>{e(n['vote_analysis'])}</vote_analysis>",
        f"          <basis>{n['basis']}</basis>",
        "          <sources>",
    ]
    for label, url in sources:
        out += ["            <source>", f"              <label>{e(label)}</label>", f"              <url>{e(url)}</url>", "            </source>"]
    out += ["          </sources>", "        </bill_note>"]
out += ["      </bill_notes>", "    </candidate>", "  </candidates>", "</election>", ""]
open(OUT, "w", encoding="utf-8").write("\n".join(out))
print(f"{len(dossier)} bill notes written to {OUT}")
