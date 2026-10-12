#!/usr/bin/env python3
"""The bills and motions a member introduced themselves, for the "introduced" card.

    python3 -I sponsored.py <ctxdir> <work-dir> "<Sponsor name as in the bill record>" "<Name on file>" <out.xml>
    python3 -I sponsored.py ctx vp-all "David Williams" "David L Williams" sponsored.xml

Bills: every bill in bills-<session>.json (bill_context.py) whose sponsor is that member.
The Legislature records one sponsor per bill; there are no co-sponsors, so none are counted.
Motions: private members' motions the member moved, read from the Votes and Proceedings
("M 205 Gavin Dew moved —"); motions that only adjourn a debate are not those.
Each item carries how far it got and a one-line context (how many MLAs sponsored a
private member's bill this Parliament, and how many became law), from the same files.
Writes an import file with a <sponsored> block. Public data only.
"""
import glob
import json
import os
import re
import sys
from xml.sax.saxutils import escape as e

ctx, work, SPONSOR, NAME, OUT = sys.argv[1:6]
roster = json.load(open(os.path.join(ctx, "roster.json")))
SESSION_NAMES = {"1st": "1st session", "2nd": "2nd session", "3rd": "3rd session"}


def session_label(key: str) -> str:
    m = re.match(r"(\d+)(?:st|nd|rd|th)(1st|2nd|3rd|4th)", key)
    ordinal = re.match(r"(\d+(?:st|nd|rd|th))", key).group(1)
    return f"{ordinal} Parliament, {SESSION_NAMES.get(key[len(ordinal):], key[len(ordinal):] + ' session')}"


def status_of(b: dict) -> tuple:
    if b.get("royal_assent"):
        return "law", f"Royal Assent {b['royal_assent']}" + (f", chapter {b['chapter']}" if b.get("chapter") else "")
    if b.get("third_reading"):
        return "third_reading", f"Third reading {b['third_reading']}"
    if b.get("committee") or b.get("report"):
        return "committee", None
    if b.get("second_reading"):
        return "second_reading", f"Second reading {b['second_reading']}"
    return "introduced", "First reading only; no further stage recorded"


# ---- context: how common private members' bills are this Parliament -------------------
all_bills = []
for path in sorted(glob.glob(os.path.join(ctx, "bills-*.json"))):
    key = os.path.basename(path)[6:-5]
    for ref, b in json.load(open(path)).items():
        all_bills.append((key, ref, b))
pmb = [x for x in all_bills if x[2]["private_member"]]
sponsors = {x[2]["sponsor"] for x in pmb if x[2]["sponsor"]}
law = [x for x in pmb if x[2].get("royal_assent")]
context = (
    f"{len(sponsors)} of {len(roster)} MLAs introduced a private member's bill this Parliament "
    f"({len(pmb)} bills in all); {len(law)} became law."
)

items = []
for key, ref, b in all_bills:
    if b["sponsor"] != SPONSOR:
        continue
    status, note = status_of(b)
    items.append({
        "kind": "bill", "reference": ref, "session": session_label(key), "title": b["title"],
        "summary": (b.get("explanatory_note") or "")[:400] or None, "introduced_on": b.get("first_reading"),
        "status": status, "status_note": note, "context": context if b["private_member"] else None,
        "source_url": b["text_url"], "source_name": "Bill text (first reading)",
    })

# ---- motions the member moved --------------------------------------------------------
pat = re.compile(r"^\s*M\s?(\d+)\s+(?:The Hon\. )?" + re.escape(SPONSOR) + r"\s+moved\s+[—-]\s*$", re.M)
for path in sorted(glob.glob(os.path.join(work, "43rd*", "v.txt"))):
    folder = os.path.basename(os.path.dirname(path))
    sess = folder.split("-")[0]
    text = open(path, encoding="utf-8", errors="ignore").read()
    for m in pat.finditer(text):
        body = re.sub(r"\s+", " ", text[m.end(): m.end() + 900].split("\n\n")[1] if "\n\n" in text[m.end(): m.end() + 900] else "")
        ref = f"Motion M {m.group(1)}"
        if any(i["reference"] == ref and i["session"] == session_label(sess) for i in items):
            continue
        date = re.search(r"v(\d{2})(\d{2})(\d{2})$", folder)
        items.append({
            "kind": "motion", "reference": ref, "session": session_label(sess), "title": f"{ref}: {body[:140]}",
            "summary": body[:400] or None, "introduced_on": f"20{date.group(1)}-{date.group(2)}-{date.group(3)}" if date else None,
            "status": "moved", "status_note": "Check the later Votes and Proceedings for the outcome",
            "context": None, "source_url": f"https://lims.leg.bc.ca/pdms/ldp/{sess}/votes/PDF/{folder.split('-', 1)[1]}.pdf",
            "source_name": f"Votes and Proceedings {folder.split('-', 1)[1]}",
        })

x = ['<?xml version="1.0" encoding="UTF-8"?>',
     f"<!-- Bills and motions {e(NAME)} introduced themselves. The Legislature records one sponsor per bill; there are no co-sponsors. -->",
     '<election id="bc-provincial-salmon-arm-shuswap">', "  <candidates>", "    <candidate>", f"      <name>{e(NAME)}</name>", "      <sponsored>"]
for i in items:
    x.append("        <item>")
    for k in ("kind", "reference", "session", "title", "summary", "introduced_on", "status", "status_note", "context", "source_url", "source_name"):
        if i.get(k):
            x.append(f"          <{k}>{e(str(i[k]))}</{k}>")
    x.append("        </item>")
x += ["      </sponsored>", "    </candidate>", "  </candidates>", "</election>", ""]
open(OUT, "w", encoding="utf-8").write("\n".join(x))
print(f"{len(items)} items ({sum(1 for i in items if i['kind']=='bill')} bills, {sum(1 for i in items if i['kind']=='motion')} motions) -> {OUT}")
