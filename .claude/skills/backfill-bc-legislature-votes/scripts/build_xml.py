#!/usr/bin/env python3
"""Turn the parsed divisions into an import file for one MLA.

    python3 -I build_xml.py <work-dir> <Surname> "<Candidate name as on file>" <out.xml> [overrides.json]

Reads <work-dir>/index.json (the sitting days), divisions.json (parse.py) and
described.json (describe.py). Writes <election id="bc-provincial-salmon-arm-shuswap"> with
a <vocabulary> of any new subject tags and one <vote> per recorded division the member
appears in (yea or nay). Divisions the member is not in are NOT written: a division
only lists members who voted, and a committee section's list holds only that section's
members, so "not listed" is not the same as "not_listed".
"""
import html, json, os, re, sys

WORK, SURNAME, NAME, OUT = sys.argv[1:5]
OVR = json.load(open(sys.argv[5])) if len(sys.argv) > 5 else {}
index = {f"{i['session']}-{i['file']}": i for i in json.load(open(os.path.join(WORK, "index.json")))}
divisions = json.load(open(os.path.join(WORK, "divisions.json")))
described = json.load(open(os.path.join(WORK, "described.json")))
e = lambda s: html.escape(s, quote=False)

# Subject tags by bill / motion topic. Existing municipal tags are reused where they truly fit.
NEW_TAGS = {
    "provincial-budget-taxes": ("taxes_budget", "Budget, taxes and supply", "Provincial budgets, supply, tax measures and carbon pricing."),
    "energy-electricity": ("infrastructure", "Energy and electricity", "Electricity generation, energy projects and energy law."),
    "infrastructure-permitting": ("infrastructure", "Infrastructure and permitting", "Provincial infrastructure projects and permitting."),
    "health-care": ("social_services", "Health care", "Health authorities, health services and health-cost law."),
    "trade-tariffs": ("economy_business", "Trade and tariffs", "Tariffs, trade recognition and procurement from other countries."),
    "children-families": ("social_services", "Children, youth and families", "Child, family and community services."),
    "legislature-process": ("governance_transparency", "Legislative process and democracy", "How the Assembly works: time allocation, deferrals, confidence and eligibility to hold office."),
    "justice-safety": ("public_safety", "Justice and public safety", "Intimate images, sexual violence and related law."),
    "human-rights": ("governance_transparency", "Human rights and equality", "The Human Rights Code and related bills and motions."),
    "consumer-business": ("economy_business", "Consumer protection and business", "Business practices, consumer protection and prompt payment."),
    "labour-employment": ("economy_business", "Labour and employment", "Employment standards and workplace law."),
}
RULES = [  # (regex on lower-cased title, tags)
    (r"carbon tax", ["provincial-budget-taxes", "climate-action"]),
    (r"budget measures|supply act|income tax", ["provincial-budget-taxes"]),
    (r"energy statutes|renewable energy|hydro|electric", ["energy-electricity"]),
    (r"infrastructure projects act", ["infrastructure-permitting"]),
    (r"mental health|perinatal|drug recovery|drug use prevention", ["mental-health-addictions"]),
    (r"health authorities|health cost|vaping|firefighters.{0,3} health", ["health-care"]),
    (r"tariff|trade recognition|secure procurement|economic stabilization", ["trade-tariffs"]),
    (r"child, family", ["children-families"]),
    (r"school amendment|schools act|in schools", ["school-governance"]),
    (r"housing", ["housing-supply"]),
    (r"freedom of information", ["transparency"]),
    (r"eligibility to hold public office|address in reply|time allocation|defer|adjourn|leave the chair|merit principle|orders of the day|subamendment|motion m ?\d+", ["legislature-process"]),
    (r"indigenous|reconciliation|treaty|land acknowledgement|aboriginal title|k.{0,2}mooks", ["reconciliation"]),
    (r"human rights code|gender|diversity, equity|motion 13", ["human-rights"]),
    (r"intimate images|sexual violence", ["justice-safety"]),
    (r"motor vehicle|dashboard cameras", ["roads-traffic"]),
    (r"business practices|consumer protection|prompt payment", ["consumer-business"]),
    (r"employment standards", ["labour-employment"]),
    (r"zero-emission", ["climate-action"]),
    (r"emergency and disaster", ["emergency-preparedness"]),
    (r"forests|protected areas", ["nature-protection"]),
]
def tags_for(title: str):
    low = title.lower()
    out = []
    for pat, ts in RULES:
        if re.search(pat, low):
            for t in ts:
                if t not in out: out.append(t)
    return out

def source_for(key: str):
    it = index[key]
    parl, sess = it["session"][:4], it["session"][4:]
    pdf = f"https://lims.leg.bc.ca/pdms/ldp/{it['session']}/votes/PDF/{it['file']}.pdf"
    name = f"Votes and Proceedings, 43rd Parliament, {sess} Session, No. {it['numbers']}"
    return pdf, name

# index the parsed divisions per sitting day for the "division k of n" note
per_day = {}
for d in divisions:
    per_day.setdefault(d["key"], []).append(d)

rows = []
for r in described:
    ovr = None
    for o in OVR.get("overrides", []):
        if (o["date"], o["vote"], o.get("for"), o.get("against")) == (r["date"], r["w"], r["for"], r["against"]) and r["text"].startswith(o.get("starts", "")):
            ovr = o; break
    title = (ovr or {}).get("title") or r["title"]
    if not title:
        raise SystemExit(f"No title for {r['date']} {r['w']} {r['for']}-{r['against']}: {r['text'][:100]}  -- add an override")
    # position of this division within the sitting day
    day = per_day[r["key"]]
    k = next(i for i, d in enumerate(day) if (d["yeas"] and d["yeas"]["declared"]) == r["for"] and (d["nays"] and d["nays"]["declared"]) == r["against"] and d["w"] == r["w"] and d is not None and not d.get("_used"))
    day[k]["_used"] = True
    rows.append({**r, "title": title, "k": k + 1, "n": len(day), "tags": (ovr or {}).get("tags") or tags_for(title),
                 "bill": (ovr or {}).get("bill"), "stage": (ovr or {}).get("stage")})

# ---- group a bill's votes: bill key, bill title and stage -------------------------------
def stage_of(title: str):
    t = title.lower()
    if re.search(r"amendment to the (second|third) reading motion", t): return "amendment"
    if re.search(r"— third reading", t): return "third_reading"
    if re.search(r"— second reading", t): return "second_reading"
    if re.search(r"first reading", t): return "first_reading"
    if re.search(r"report stage", t): return "report_stage"
    if re.search(r"committal", t): return "committal"
    if re.search(r"— amendment", t): return "amendment"
    if re.search(r"— clauses? ", t) or re.search(r"— the title", t): return "clause"
    return None

def bill_of(title: str):
    m = re.match(r"^(Bill M?\d+): (.+?)(?: — |$)", title)
    if m: return m.group(1), m.group(2)
    m = re.match(r"^(Bill M?\d+)(?: — |$)", title)
    if m: return m.group(1), None
    m = re.match(r"^(.+?Act(?:[^—]*?))(?: — first reading|$)", title)
    if m and "first reading" in title: return None, m.group(1).strip()   # a private member's bill with no number yet
    return None, None

names = {}   # (session, "Bill 7") -> "Bill 7: Name"
for r in rows:
    ref, nm = bill_of(r["title"])
    if ref and nm: names[(r["key"].split("-")[0], ref)] = f"{ref}: {nm}"
for r in rows:
    session = r["key"].split("-")[0]
    ref, nm = bill_of(r["title"])
    ovr_bill = r.get("bill")
    if ovr_bill:
        ref, nm = ovr_bill, None
    if ref:
        r["bill_key"] = f"{session}:{ref}"
        r["bill_title"] = names.get((session, ref)) or (f"{ref}: {nm}" if nm else ref)
        r["bill_ref"] = ref
    elif nm:
        r["bill_key"] = f"{session}:{nm}"; r["bill_title"] = nm; r["bill_ref"] = None
    else:
        r["bill_key"] = r["bill_title"] = r["bill_ref"] = None
    r["stage"] = r.get("stage") or (stage_of(r["title"]) if r["bill_key"] else None)

used = sorted({t for r in rows for t in r["tags"] if t in NEW_TAGS})
x = ['<?xml version="1.0" encoding="UTF-8"?>',
     f'<!-- Recorded divisions in the BC Legislative Assembly (43rd Parliament, Feb. 2025 to May 2026) in which {NAME} is listed, read from the published Votes and Proceedings. A division lists only the members who voted; a committee section lists only its own members. Divisions the member is not listed in are not recorded here. Tallies are the Yeas and Nays counts printed in the record. -->',
     '<election id="bc-provincial-salmon-arm-shuswap">']
if used:
    x.append("  <vocabulary>")
    for slug in used:
        topic, nm, desc = NEW_TAGS[slug]
        x.append(f"    <tag><slug>{slug}</slug><kind>subject</kind><topic>{topic}</topic><name>{e(nm)}</name><description>{e(desc)}</description></tag>")
    x.append("  </vocabulary>")
x += ["  <candidates>", "    <candidate>", f"      <name>{e(NAME)}</name>", "      <votes>"]
for r in rows:
    pdf, src = source_for(r["key"])
    ref = r["ref"] if r.get("ref") and not str(r["ref"]).startswith("Bill (") else None
    desc = r["text"]
    # Keep the wording of this division only: drop an earlier division's sentence stuck on the
    # front, and a Message from the Lieutenant Governor that precedes a first-reading motion.
    parts = re.split(r"(?<=following division:)\s+|(?<=following deferred division:)\s+", desc)
    if len(parts) > 1:
        desc = parts[-1] if len(parts[-1]) > 40 else " ".join(parts[-2:])
    if desc.startswith("The Lieutenant Governor") and " On the motion " in desc:
        desc = desc[desc.index(" On the motion ") + 1:]
    desc = re.sub(r"\s+", " ", desc).strip()
    if len(desc) > 700: desc = desc[:697].rstrip() + "…"
    desc += f" [Division {r['k']} of {r['n']} recorded on {r['date']}.]"
    tf = r["for"] if r["for"] is not None else (0 if r["against"] is not None else None)
    ta = r["against"] if r["against"] is not None else (0 if r["for"] is not None else None)
    x.append("        <vote>")
    x.append("          <body>legislature</body>")
    x.append(f"          <voted_on>{r['date']}</voted_on>")
    x.append(f"          <title>{e(r['title'])}</title>")
    if ref: x.append(f"          <reference>{e(ref)}</reference>")
    if r.get("bill_key"):
        x.append(f"          <bill_key>{e(r['bill_key'])}</bill_key>")
        x.append(f"          <bill_title>{e(r['bill_title'])}</bill_title>")
    if r.get("stage"): x.append(f"          <stage>{r['stage']}</stage>")
    x.append(f"          <description>{e(desc)}</description>")
    x.append(f"          <vote>{'yea' if r['w'] == 'yea' else 'nay'}</vote>")
    if r.get("outcome"): x.append(f"          <outcome>{r['outcome']}</outcome>")
    if tf is not None: x.append(f"          <tally_for>{tf}</tally_for>")
    if ta is not None: x.append(f"          <tally_against>{ta}</tally_against>")
    x.append(f"          <source_url>{e(pdf)}</source_url>")
    x.append(f"          <source_name>{e(src)}</source_name>")
    if r["tags"]: x.append("          <tags>" + "".join(f"<tag>{t}</tag>" for t in r["tags"]) + "</tags>")
    x.append("        </vote>")
x += ["      </votes>", "    </candidate>", "  </candidates>", "</election>", ""]
open(OUT, "w", encoding="utf-8").write("\n".join(x))
from collections import Counter
bills = {r["bill_key"] for r in rows if r.get("bill_key")}
print(len(bills), "bills;", Counter(r.get("stage") for r in rows), end=" | ")
print(len(rows), "votes;", Counter(r["w"] for r in rows), "| tagged:", sum(1 for r in rows if r["tags"]), "| new tags used:", used)
