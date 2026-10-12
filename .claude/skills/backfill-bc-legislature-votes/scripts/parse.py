import json, os, re, sys
from collections import Counter
BASE = sys.argv[1]
index = {f"{i['session']}-{i['file']}": i for i in json.load(open(os.path.join(BASE, "index.json")))}
HEAD = re.compile(r"^\s*(Yeas|Nays)\s+[—-]\s+(\d+)\s*$")
PAGE = re.compile(r"^\s*(\d+\s+)?(January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4}(\s+\d+)?\s*$|^\s*\d+\s*$")
def clean(l): return re.sub(r"\s+", " ", l).strip()
def is_names_row(l):
    # surnames/initials separated by 2+ spaces; no sentence punctuation or small words
    if not l.strip(): return False
    if re.search(r"\b(the|was|were|and|on|of|to|House|Bill|motion|Motion|passed|agreed|moved|resumed|debate)\b", l): return False
    return bool(re.fullmatch(r"[\sA-Za-zÀ-ÿ'’.\-]+", l))
def names_from(rows):
    out = []
    for ln in rows:
        out.extend(p.strip() for p in re.split(r"\s{2,}", ln.strip()) if p.strip())
    return out
divisions = []
for key, meta in sorted(index.items(), key=lambda kv: (kv[1]["date"], kv[0])):
    path = os.path.join(BASE, key, "v.txt")
    if not os.path.exists(path): continue
    lines = open(path, encoding="utf-8", errors="ignore").read().split("\n")
    i = 0
    while i < len(lines):
        m = HEAD.match(lines[i])
        if not m:
            i += 1; continue
        start = i
        blocks = {}
        while i < len(lines):
            m = HEAD.match(lines[i])
            if not m:
                # the Yeas and Nays blocks can be split by blank lines and a page footer
                # ("2   April 3, 2025"), so look a few lines ahead past that noise
                j = i
                while j < len(lines) and j - i < 8 and (not lines[j].strip() or PAGE.match(re.sub(r'[\x00-\x1f]', ' ', lines[j]).strip())): j += 1
                if j < len(lines) and HEAD.match(lines[j]) and j - i < 8: i = j; continue
                break
            kind = m.group(1); n = int(m.group(2)); i += 1
            rows = []
            while i < len(lines) and is_names_row(lines[i]) and not HEAD.match(lines[i]):
                rows.append(lines[i]); i += 1
            blocks[kind] = {"declared": n, "names": names_from(rows)}
        # Keep the paragraph breaks (blank lines): the item being voted on is the paragraph(s) just above.
        back = []
        j = start - 1
        nonblank = 0
        while j >= 0 and nonblank < 40:
            raw = re.sub(r"[\x00-\x1f]", " ", lines[j])
            # name rows of an earlier division, its Yeas/Nays headers and page footers are not the question
            if raw.strip() and (HEAD.match(raw) or PAGE.match(raw.strip()) or (is_names_row(raw) and (re.search(r"\s{2,}", raw.strip()) or len(raw.split()) <= 2 and len(raw.strip()) < 24))):
                if back and back[-1] != "": back.append("")
            elif raw.strip():
                back.append(clean(raw)); nonblank += 1
            elif back and back[-1] != "":
                back.append("")
            j -= 1
        back.reverse()
        divisions.append({"key": key, "date": meta["date"], "context": back, "yeas": blocks.get("Yeas"), "nays": blocks.get("Nays")})
bad = []
for d in divisions:
    for side in ("yeas", "nays"):
        b = d[side]
        if b and len(b["names"]) != b["declared"]: bad.append((d["key"], side, b["declared"], len(b["names"])))
    ys = d["yeas"]["names"] if d["yeas"] else []
    ns = d["nays"]["names"] if d["nays"] else []
    d["w"] = "yea" if "Williams" in ys else "nay" if "Williams" in ns else "not_listed"
json.dump(divisions, open(os.path.join(BASE, "divisions.json"), "w"), ensure_ascii=False, indent=1)
print("divisions:", len(divisions), "| count mismatches:", len(bad))
print(Counter(d["w"] for d in divisions))
for b in bad[:6]: print("  mismatch", b)
