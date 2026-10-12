#!/usr/bin/env python3
"""Pull each bill's own facts from the Legislature's Progress of Bills: sponsor,
reading dates, Royal Assent, and the bill's Explanatory Note.

    python3 -I bill_context.py <outdir> <session> <progress-id>
    python3 -I bill_context.py out 43rd1st 173
    python3 -I bill_context.py out 43rd2nd 206

The progress id is the number in the page's XHR
(https://api.lims.leg.bc.ca/pdms/bills/progress-of-bills/<id>); read it from the
bills page's network log with playwright-fetch.cjs (43rd1st = 173, 43rd2nd = 206).
Writes <outdir>/bills-<session>.json keyed by "Bill 7" / "Bill M202". Public pages only.
"""
import html
import json
import re
import sys
import time
import urllib.request

UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"


def get(url: str) -> str:
    time.sleep(0.4)
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=90) as r:
        return r.read().decode("utf-8", "ignore")


def text_of(fragment: str) -> str:
    return html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", fragment))).strip()


def explanatory_note(page: str) -> str:
    """The bill's Explanatory Note, as the sponsor wrote it (class 'explannote')."""
    paras = re.findall(r'<p class="explannote[^"]*"[^>]*>(.*?)</p>', page, re.S)
    if not paras:
        m = re.search(r"(?is)explanatory note(.{0,3000}?)(?:her majesty|\bhis majesty|<h\d)", page)
        paras = [m.group(1)] if m else []
    note = " ".join(text_of(p) for p in paras).strip()
    return re.sub(r'^.*?Explanatory Note Icon"\s*/?>\s*', "", note)


def main() -> None:
    outdir, session, pid = sys.argv[1:4]
    bills = json.loads(get(f"https://api.lims.leg.bc.ca/pdms/bills/progress-of-bills/{pid}"))
    out = {}
    for b in bills:
        n = b["billNumber"]
        ref = f"Bill M{n}" if b.get("billTypeId") == 2 else f"Bill {n}"
        first = next((f for f in b["files"]["nodes"] if f["readingTypeName"] == "1st Reading"), None)
        note = ""
        if first:
            try:
                note = explanatory_note(get("https://lims.leg.bc.ca/pdms" + first["path"]))
            except Exception as e:  # a missing file shouldn't stop the run
                note = f"(could not fetch: {e})"
        out[ref] = {
            "title": b["title"],
            "sponsor": b.get("memberAlias"),
            "private_member": b.get("billTypeId") == 2,
            "first_reading": next((f["readingDate"] for f in b["files"]["nodes"] if f["readingTypeName"] == "1st Reading"), None),
            "second_reading": b.get("secondReading"),
            "committee": b.get("committeeReading"),
            "report": b.get("reportReading"),
            "third_reading": b.get("thirdReading"),
            "royal_assent": b.get("royalAssent"),
            "chapter": b.get("chapterNumber"),
            "ruled_out_of_order": b.get("ruledOutOfOrder"),
            "explanatory_note": note,
            "text_url": ("https://lims.leg.bc.ca/pdms" + first["path"]) if first else None,
        }
    path = f"{outdir}/bills-{session}.json"
    json.dump(out, open(path, "w"), ensure_ascii=False, indent=1)
    with_note = sum(1 for v in out.values() if v["explanatory_note"])
    print(f"{session}: {len(out)} bills, {with_note} with an explanatory note -> {path}")


if __name__ == "__main__":
    main()
