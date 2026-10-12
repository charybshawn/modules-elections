#!/usr/bin/env python3
"""The mover's statement when a bill with no progress-of-bills record was introduced
(opposition bills that stop at first reading carry no explanatory note or number).

    python3 -I intro_statement.py <outdir> <votes.xml>[,<more.xml>] [--only "<bill_key>|<bill_key>"]
    python3 -I intro_statement.py ctx votes.xml

For every bill key in the votes file that has no sponsor second-reading opening on file
(sponsor-<session>.json), finds the first-reading vote's day in Hansard, finds the heading carrying the bill's
title, and keeps what the mover said there (up to ~2,500 characters). Writes
<outdir>/intro-statements.json keyed by bill_key. Public pages only.
"""
import html
import json
import re
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cache  # noqa: E402  (shared persistent cache)
import xml.etree.ElementTree as ET

UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
LIMS = "https://lims.leg.bc.ca/hdms/file/"
API = "https://api.lims.leg.bc.ca/hdms/"


def get(url, ttl=None):
    return cache.fetch(url, ttl=ttl)

def text_of(fragment):
    return html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", fragment))).strip()


def day_files(session):
    nodes = json.loads(get(f"{API}debates/{session}", ttl=6 * 3600))["allHansardFileAttributes"]["nodes"]
    out = {}
    for n in nodes:
        for da in (n.get("debateAttributes") or {}).get("nodes") or []:
            link = (da.get("redirectLink") or "").lstrip("/")
            m = re.search(r"/(\d{4})(\d{2})(\d{2})(?:am|pm)?-Hansard-n\d+\.html", "/" + link)
            if m:
                out.setdefault(f"{m.group(1)}-{m.group(2)}-{m.group(3)}", set()).add(link)
    return {k: sorted(v) for k, v in out.items()}


def statement(page, title):
    words = re.sub(r"[^A-Za-z ]", "", title).lower().split()
    needle = " ".join(words[:4])
    for m in re.finditer(r'<p class="Subject-Heading"[^>]*id="[^"]+"[^>]*>(.*?)</p>', page, re.S):
        h = re.sub(r"[^A-Za-z ]", "", text_of(m.group(1))).lower()
        if needle not in re.sub(r"\s+", " ", h):
            continue
        tail = page[m.end() : m.end() + 40000]
        stop = re.search(r'<p class="(?:Subject-Heading|Business-Heading)"', tail)
        seg = tail[: stop.start()] if stop else tail
        paras = re.findall(r'<p class="Speaker(?:Begins[^"]*|Continues)"[^>]*>(.*?)</p>', seg, re.S)
        if paras:
            who = re.search(r'class="Speaker-Name">(.*?)</span>', seg, re.S)
            body = " ".join(text_of(re.sub(r'^.*?</span>\s*<span class="Bold">:</span>', "", p, flags=re.S)) for p in paras[:6])
            return (text_of(who.group(1)) + ": " if who else "") + body[:2500]
    return None


def main():
    args = sys.argv[1:]
    only = None
    if "--only" in args:  # only these bill keys, "|"-separated (a key can contain commas)
        i = args.index("--only")
        only = {x.strip() for x in args[i + 1].split("|") if x.strip()}
        del args[i:i + 2]
    outdir, votes_xml = args[0:2]
    have = {}
    import glob, os
    # a bill needs an introduction statement unless its sponsor's second-reading opening is on file
    for p in glob.glob(f"{outdir}/sponsor-*.json"):
        have[os.path.basename(p)[8:-5]] = json.load(open(p))
    todo = {}
    for v in (v for p in [q for part in votes_xml.split(",") for q in sorted(glob.glob(part))] for v in ET.parse(p).getroot().iter("vote")):
        key = (v.findtext("bill_key") or "").strip()
        if not key:
            continue
        session, ref = key.split(":", 1)
        if (only is not None and key not in only) or ref in have.get(session, {}):
            continue
        day = (v.findtext("voted_on") or "").strip()
        title = (v.findtext("bill_title") or ref).strip()
        if key not in todo or (v.findtext("stage") == "first_reading"):
            todo[key] = (session, day, title)
    pages, days = {}, {}
    target = f"{outdir}/intro-statements.json"
    out = json.load(open(target)) if os.path.exists(target) else {}
    for key, (session, day, title) in todo.items():
        if key in out:
            continue
        if session not in days:
            days[session] = day_files(session)
        for path in days[session].get(day, []):
            if path not in pages:
                pages[path] = get(LIMS + path)
            s = statement(pages[path], title)
            if s:
                out[key] = {"date": day, "title": title, "text": s, "url": LIMS + path}
                break
    json.dump(out, open(f"{outdir}/intro-statements.json", "w"), ensure_ascii=False, indent=1)
    print(f"{len(out)} of {len(todo)} bills without a progress record have an introduction statement -> {outdir}/intro-statements.json")


if __name__ == "__main__":
    main()
