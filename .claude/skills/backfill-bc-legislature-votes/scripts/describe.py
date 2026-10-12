import json, os, re, sys
BASE, SURNAME = sys.argv[1], sys.argv[2]
D = json.load(open(os.path.join(BASE, "divisions.json")))
# Bill names by session, from every "Bill (No. N) intituled NAME" anywhere in that session's documents.
BILLNAMES = {}
_NAMEPAT = re.compile(r"Bill \((?:No\. ?)?(M ?\d+|\d+)\)\s+intituled\s+(.+?Act(?:(?: \(No\. \d+\))?, \d{4})?)(?=[\s,.]|$)")
for _d in sorted(os.listdir(BASE)):
    _p = os.path.join(BASE, _d, "v.txt")
    if not os.path.exists(_p): continue
    _sess = _d.split("-")[0]
    _t = re.sub(r"\s+", " ", open(_p, encoding="utf-8", errors="ignore").read())
    for _m in _NAMEPAT.finditer(_t):
        BILLNAMES.setdefault((_sess, "Bill " + _m.group(1).replace(" ", "")), _m.group(2).strip())
MONTHS = "January|February|March|April|May|June|July|August|September|October|November|December"
NOISE = re.compile(rf"^(({MONTHS}) \d{{1,2}}, \d{{4}} \d+|\d+ ({MONTHS}) \d{{1,2}}, \d{{4}}|\(In Committee.*\)|\d+|{SURNAME})$")
def paragraphs(lines):
    paras, cur = [], []
    for l in lines + [""]:
        if l == "":
            if cur: paras.append(" ".join(cur)); cur = []
        elif not NOISE.match(l):
            cur.append(l)
    return paras
SUBJECT = re.compile(r"(Bill \(No\.|Motion \d+|moved|On the motion|Standing Order|In consideration|Address in Reply|question of privilege|Committee|clause|amendment)", re.I)
BARE = re.compile(r"^(Motion|Amendment|The motion|The amendment|Question|Clause|Clauses|Bill|Section)\b[^.]{0,80}\b(division|divided)", re.I)
def describe(d):
    paras = paragraphs(d["context"])
    paras = [p for p in paras if not re.fullmatch(r"The House divided\.?", p) and not re.fullmatch(r"A debate arose\.?", p)]
    if not paras: return {"text": "", "outcome": None, "ref": None, "name": None, "stage": None, "title": None}
    out = paras[-1]
    item = [out]
    k = len(paras) - 2
    while k >= 0 and len(item) < 4 and (BARE.match(item[0]) or len(" ".join(item)) < 90 or not SUBJECT.search(" ".join(item))):
        item.insert(0, paras[k]); k -= 1
    text = re.sub(r"\s+", " ", " ".join(item)).strip()
    low = out.lower()
    outcome = None
    if re.search(r"negatived|defeated|\blost\b|rejected|not agreed", low): outcome = "defeated"
    elif re.search(r"agreed to|passed|carried|adopted", low): outcome = "passed"
    ref = name = stage = mover = None
    NAME = r"intituled (.+?Act(?:(?: \(No\. \d+\))?, \d{4})?)(?=[\s,.]|$)"
    m = re.findall(r"Bill \((?:No\. ?)?(M ?\d+|\d+)\)", text)
    if m:
        ref = "Bill " + m[-1].replace(" ", "")
        n = re.findall(r"Bill \((?:No\. ?)?" + re.escape(m[-1]) + r"\)[^.]{0,14}?" + NAME, text)
        if n: name = n[-1].strip()
    else:
        mm = re.search(r"(Motion \d+)", text)
        if mm: ref = mm.group(1)
        n = re.search(r"a bill " + NAME, text)
        if n: name = n.group(1).strip()
    intro = re.search(r"On the motion of (?:the Hon\. )?([A-Z][^,]*?),? that (?:a )?[Bb]ill", text)
    if intro: mover = intro.group(1).strip()
    am = re.search(r"(?:^|\. )(?:The )?(?:Hon\. )?([A-Z][\w’'.\- ]+?)(?: \([^)]*\))? moved (?:a|the) (?:proposed )?amendment", text) or re.search(r"proposed amendment (?:moved )?by (?:the )?(?:Hon\. )?([A-Z][\w’'.\- ]+?)(?: \([^)]*\))? (?:to|on)", text)
    if am: mover = am.group(1).strip()
    # The question put is in the last sentence that mentions the division.
    sents = re.split(r"(?<=[.:])\s+(?=[A-Z(“\"])", text)
    last = next((x for x in reversed(sents) if re.search(r"division|divided", x, re.I)), text)
    if len(last) < 40 and len(sents) > 1:
        last = " ".join(sents[-2:])
    if ref and not name:
        name = BILLNAMES.get((d["key"].split("-")[0], ref))
    reasoned = re.search(r"amendment (?:on|to) the motion for (second|third) reading", text, re.I) or re.search(r"subamendment|reasoned amendment", text, re.I)
    for pat, label in [(r"third reading", "third reading"), (r"second reading", "second reading"), (r"introduced and read a first time|first time", "first reading"), (r"(clauses? \d+(?:\.\d+)?|Schedule [^ ,]+|Section [^ ,]+)", None), (r"amendment", "amendment"), (r"concurred in|report stage", "report stage"), (r"committal|committed", "committal")]:
        x = re.search(pat, last, re.I) or re.search(pat, text, re.I)
        if x: stage = label or x.group(1); break
    motion = re.search(r"Motion (?:M ?)?\d+ (regarding [^,]+?)(?:, the House divided|, the House|\. | the House divided)", text)
    amend = re.search(r"amendment[^.]{0,100}?(clauses? \d+(?:\.\d+)?|the title|Schedule [^ ,]+|Section [^ ,]+)", last, re.I) or re.search(r"amendment[^.]{0,100}?(clauses? \d+(?:\.\d+)?|the title|Schedule [^ ,]+|Section [^ ,]+)", text, re.I)
    clause_vote = re.search(r"(?:^|\. )(?:In consideration of )?(clauses? \d+(?:\.\d+)?|the title)[^.]{0,30}?(?:of|to) Bill", last, re.I)
    base = f"{ref}: {name}" if (ref and name) else (ref or name)
    title = None
    if ref and ref.startswith("Motion") and motion and not name:
        title = f"{ref} {motion.group(1)}"
    elif base:
        if re.match(r"^(?:The )?(?:Clauses? \d+(?:\.\d+)?|the title)\b[^.]*?\b(?:of|to) Bill", last, re.I) and re.search(r"passed|agreed|carried", last, re.I) and not re.search(r"amendment", last.split("Bill")[0], re.I):
            cv = re.match(r"^(?:The )?(Clauses? \d+(?:\.\d+)?|the title)", last, re.I)
            title = f"{base} — {cv.group(1).lower()}"
        elif reasoned and not (mover and amend): title = f"{base} — amendment to the {('third' if 'third' in text.lower()[:text.lower().find('amendment')+80] and 'third reading' in text.lower() else 'second')} reading motion"
        elif ref and ref.startswith("Motion") and motion: title = f"{ref} {motion.group(1)}"
        elif clause_vote and not amend: title = f"{base} — {clause_vote.group(1).lower()}"
        elif mover and amend: title = f"{base} — amendment to {amend.group(1).lower()}, moved by {mover}"
        elif amend and re.search(r"amendment", text, re.I) and stage not in ("third reading", "second reading", "first reading"): title = f"{base} — amendment to {amend.group(1).lower()}"
        elif stage == "first reading" and mover: title = f"{base} — first reading, introduced by {mover}"
        else: title = base + (f" — {stage}" if stage else "")
    return {"text": text, "outcome": outcome, "ref": ref, "name": name, "stage": stage, "mover": mover, "title": title}
rows = []
for d in D:
    if d["w"] == "not_listed": continue
    r = describe(d)
    rows.append({**{k: d[k] for k in ("key", "date", "w")}, "for": d["yeas"]["declared"] if d["yeas"] else None, "against": d["nays"]["declared"] if d["nays"] else None, **r})
json.dump(rows, open(os.path.join(BASE, "described.json"), "w"), ensure_ascii=False, indent=1)
print(len(rows), "votes;", sum(1 for r in rows if r["title"]), "titled;", sum(1 for r in rows if not r["outcome"]), "without outcome")
