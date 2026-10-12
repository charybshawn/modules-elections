#!/usr/bin/env python3
"""The sponsor's opening second-reading speech for each bill: what the bill is for,
in the words of the minister or member who introduced it.

    python3 -I sponsor_speech.py <outdir> <session>        # needs bills-<session>.json (bill_context.py)
    python3 -I sponsor_speech.py ctx 43rd1st

For each bill with a second-reading date, finds that day's transcript issues, finds the
"Bill N — Title" heading under Second Reading of Bills and keeps the first speaker's
opening (up to ~2,500 characters). Writes <outdir>/sponsor-<session>.json keyed by
"Bill 7" / "Bill M202". Bills read without debate have none. Public pages only.
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


def day_files(session: str) -> dict[str, list[str]]:
    """YYYY-MM-DD -> transcript paths (a sitting day can have a.m. and p.m. issues)."""
    nodes = json.loads(get(f"{API}debates/{session}"))["allHansardFileAttributes"]["nodes"]
    out: dict[str, set[str]] = {}
    for n in nodes:
        for da in (n.get("debateAttributes") or {}).get("nodes") or []:
            link = (da.get("redirectLink") or "").lstrip("/")
            m = re.search(r"/(\d{4})(\d{2})(\d{2})(?:am|pm)?-Hansard-n\d+\.html", "/" + link)
            if m:
                out.setdefault(f"{m.group(1)}-{m.group(2)}-{m.group(3)}", set()).add(link)
    return {k: sorted(v) for k, v in out.items()}


def opening(page, ref, sponsor):
    """The sponsor's first speech under a "Bill N —" heading in this issue, or None."""
    num = ref.split()[1]
    surname = (sponsor or "").split()[-1] if sponsor else ""
    for m in re.finditer(r'<p class="Subject-Heading"[^>]*id="[^"]+"[^>]*>(.*?)</p>', page, re.S):
        if not re.search(rf"Bill {re.escape(num)}\b", text_of(m.group(1))):
            continue
        tail = page[m.end() : m.end() + 90000]
        stop = re.search(r'<p class="(?:Subject-Heading|Business-Heading)"', tail)
        seg = tail[: stop.start()] if stop else tail
        first = re.search(r'<p class="SpeakerBegins[^"]*"[^>]*>(.*?)</p>(.*)', seg, re.S)
        if not first:
            continue
        who = re.search(r'class="Speaker-Name">(.*?)</span>', first.group(1), re.S)
        name = text_of(who.group(1)) if who else ""
        if surname and surname not in name:
            continue
        paras = [re.sub(r'^.*?</span>\s*<span class="Bold">:</span>', "", first.group(1), flags=re.S)]
        paras += re.findall(r'<p class="SpeakerContinues"[^>]*>(.*?)</p>', first.group(2), re.S)[:5]
        return name + ": " + " ".join(text_of(x) for x in paras)[:2500]
    return None


def main():
    outdir, session = sys.argv[1:3]
    bills = json.load(open(f"{outdir}/bills-{session}.json"))
    files = day_files(session)
    days = sorted(files)
    cache = {}
    out = {}
    for ref, b in bills.items():
        lo, hi = b.get("first_reading"), b.get("second_reading")
        if not hi:
            continue
        for day in (d for d in days if (lo or d) <= d <= hi):
            for path in files[day]:
                if path not in cache:
                    cache[path] = get(LIMS + path)
                o = opening(cache[path], ref, b.get("sponsor"))
                if o:
                    out[ref] = {"date": day, "text": o, "url": LIMS + path}
                    break
            if ref in out:
                break
    json.dump(out, open(f"{outdir}/sponsor-{session}.json", "w"), ensure_ascii=False, indent=1)
    print(f"{session}: {len(out)} of {len(bills)} bills have a sponsor's second-reading opening -> {outdir}/sponsor-{session}.json")


if __name__ == "__main__":
    main()
