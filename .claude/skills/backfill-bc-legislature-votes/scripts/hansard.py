#!/usr/bin/env python3
"""Pull one MLA's own words on each bill from the published Hansard.

    python3 -I hansard.py <outdir> <session> <member-in-index> <speaker-as-spoken>
    python3 -I hansard.py out 43rd1st "Williams, David" "David Williams"
    python3 -I hansard.py out 43rd2nd "Williams, David" "David Williams"

Reads the session's Members Index (who spoke on which bill, in which issue and at
what time), downloads only those transcript issues, and writes
<outdir>/hansard-<session>.json:

    {"Bill 31": {"title": "...", "speeches": [{"issue": 88, "date": "...", "time": "14:50",
                                               "heading": "...", "url": "...", "text": "..."}]}}

Public pages only. Standard library only. Pause between requests.
"""
import html
import json
import re
import sys
import time
import urllib.request

UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
LIMS = "https://lims.leg.bc.ca/hdms/file/"
API = "https://api.lims.leg.bc.ca/hdms/"


def get(url: str) -> str:
    time.sleep(0.4)
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=90) as r:
        return r.read().decode("utf-8", "ignore")


def text_of(fragment: str) -> str:
    return html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", fragment))).strip()


def issue_files(session: str) -> dict[int, str]:
    """issue number -> transcript path, from the debates feed's redirect links."""
    nodes = json.loads(get(f"{API}debates/{session}"))["allHansardFileAttributes"]["nodes"]
    out: dict[int, str] = {}
    for n in nodes:
        for da in (n.get("debateAttributes") or {}).get("nodes") or []:
            m = re.search(r"Hansard-n(\d+)\.html", da.get("redirectLink") or "")
            if m:
                out.setdefault(int(m.group(1)), (da["redirectLink"]).lstrip("/"))
    return out


def member_block(session: str, member: str) -> tuple[str, str]:
    """(index year, the member's block of the Members Index, as lines)."""
    idx = json.loads(get(f"{API}index/{session}"))["allHansardFileAttributes"]["nodes"]
    main = next(n for n in idx if "members-index" in n["fileName"].lower())
    base = main["filePath"].lstrip("/")
    year = main["fileName"][:4]
    page = get(f"{LIMS}{base}/{main['fileName']}")
    m = re.search(r'<a href="([^"]+)"[^>]*><b>' + re.escape(member), page)
    if not m:
        sys.exit(f"{member!r} not found in the {session} Members Index")
    href, anchor = m.group(1).split("#")
    letter_page = get(f"{LIMS}{base}/{href}")
    i = letter_page.index(f'name="{anchor}"')
    j = letter_page.find('name="mh', i + 10)
    block = letter_page[i : j if j > 0 else len(letter_page)]
    lines = [text_of(x) for x in re.split(r"<br ?/?>|</p>|</li>|</ul>", block)]
    return year, "\n".join(l for l in lines if l)


def bills_in(block: str) -> dict[str, dict]:
    """Bill ref -> {title, refs:[(issue, section, 'HH:MM')]} from the index block.

    The block flattens to one run of text: "... Budget Measures Implementation Act, 2025
    (Bill 5) debate 45B:1620-1640 , 47B:1555-1615 Carbon Tax Amendment Act ..." -- so a bill's
    title is the words after the previous reference, and its references are the tokens
    that follow its "(Bill N)" marker until the next ordinary word.
    """
    toks = re.sub(r"\(Bill (M?\d+)\)", r"(Bill:\1)", block).replace(",", " , ").split()
    ref_re = re.compile(r"^\d+[ABC]:\d{4}(?:-\d{4})?$")
    filler = re.compile(r"^(?:,|debate|amdts\.?|cl\.|and|1R|2R|3R|C|RA|[\d\-–]+)$", re.I)
    out: dict[str, dict] = {}
    for i, t in enumerate(toks):
        m = re.match(r"^\(Bill:(M?\d+)\)$", t)
        if not m:
            continue
        j = i
        while j > 0 and not ref_re.match(toks[j - 1]) and toks[j - 1] != "debate":
            j -= 1
        title = " ".join(toks[j:i])
        refs, k = [], i + 1
        while k < len(toks) and (ref_re.match(toks[k]) or filler.match(toks[k])):
            r = re.match(r"^(\d+)([ABC]):(\d{4})", toks[k])
            if r:
                refs.append((int(r.group(1)), r.group(2), f"{r.group(3)[:2]}:{r.group(3)[2:]}"))
            k += 1
        out[f"Bill {m.group(1)}"] = {"title": title, "refs": refs}
    return out


def speeches_in(issue_html: str, speaker: str) -> list[dict]:
    """Each speech by `speaker` in one transcript issue: heading, time, full text."""
    tokens = re.finditer(
        r'id="(\d+[ABC]:\d{4})"|<p class="([^"]*)"([^>]*)>(.*?)</p>', issue_html, re.S
    )
    heading, clock, out, cur = "", "", [], None
    for t in tokens:
        if t.group(1):
            clock = t.group(1).split(":")[1]
            clock = f"{clock[:2]}:{clock[2:]}"
            continue
        cls, body = t.group(2), t.group(4)
        if cls in ("Subject-Heading", "Business-Heading") and "href" not in t.group(3):
            heading = text_of(body)
        if cls.startswith("SpeakerBegins"):
            # the speech's own time stamp is the last anchor before it in the page
            prior = re.findall(r'id="\d+[ABC]:(\d{4})"', issue_html[: t.start()])
            clock = f"{prior[-1][:2]}:{prior[-1][2:]}" if prior else ""
            name = text_of(re.search(r'class="Speaker-Name">(.*?)</span>', body, re.S).group(1)) if "Speaker-Name" in body else ""
            cur = None
            if name.endswith(speaker) or speaker in name:
                cur = {"heading": heading, "time": clock, "paras": [text_of(re.sub(r'^.*?</span>\s*<span class="Bold">:</span>', "", body, flags=re.S))]}
                out.append(cur)
        elif cls.startswith("SpeakerContinues") and cur is not None:
            cur["paras"].append(text_of(body))
        elif cls.startswith(("Speaker", "Subject", "Business", "Proceedings")) and not cls.startswith("SpeakerContinues"):
            cur = None
    return [{"heading": s["heading"], "time": s["time"], "text": " ".join(p for p in s["paras"] if p)} for s in out]


def main() -> None:
    outdir, session, member, speaker = sys.argv[1:5]
    files = issue_files(session)
    year, block = member_block(session, member)
    bills = bills_in(block)
    cache: dict[int, str] = {}
    result: dict[str, dict] = {}
    for ref, b in bills.items():
        speeches, seen = [], set()
        for issue, _sec, _hhmm in b["refs"]:
            if issue not in files:
                continue
            if issue not in cache:
                cache[issue] = get(LIMS + files[issue])
            page = cache[issue]
            date = re.search(r'name="dc.date" content="([^"]+)"', page)
            for sp in speeches_in(page, speaker):
                num = ref.split()[1]
                if not (re.search(rf"Bill {re.escape(num)}\b", sp["heading"]) or b["title"][:25].lower() in sp["heading"].lower()):
                    continue
                key = (issue, sp["time"], sp["text"][:60])
                if key in seen:
                    continue
                seen.add(key)
                speeches.append({"issue": issue, "date": date.group(1) if date else None, **sp, "url": LIMS + files[issue]})
        result[ref] = {"title": b["title"], "speeches": speeches}
    path = f"{outdir}/hansard-{session}.json"
    json.dump(result, open(path, "w"), ensure_ascii=False, indent=1)
    spoken = sum(1 for v in result.values() if v["speeches"])
    print(f"{session}: {len(result)} bills in the index, {spoken} with a speech found, {len(cache)} issues read -> {path}")


if __name__ == "__main__":
    main()
