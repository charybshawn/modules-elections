#!/usr/bin/env python3
"""Sittings of a Parliament's sessions, and the Votes and Proceedings for the ones asked for.

    python3 -I fetch.py <work-dir> [sessions...]                         # every sitting (a first backfill)
    python3 -I fetch.py <work-dir> --after 2026-05-28 [sessions...]      # only sittings after a date
    python3 -I fetch.py <work-dir> --days 2025-04-03,2025-04-16 [sessions...]   # exactly these days
    python3 -I fetch.py <work-dir> --list [sessions...]                  # just list sittings (one cheap request)

Writes <work-dir>/sittings.json (every sitting the Legislature lists) and <work-dir>/index.json (the
sittings selected), then, for each selected sitting, <key>/v.pdf and <key>/v.txt, read through the
shared cache (cache.py) so a PDF is downloaded once, ever, for every candidate. The sittings list
is re-checked at most every six hours. Sessions default to both of the 43rd Parliament.
"""
import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cache  # noqa: E402

DEFAULT_SESSIONS = ["43rd1st", "43rd2nd"]


def sittings(sessions):
    """Every sitting the Legislature lists for these sessions, oldest first."""
    out = []
    for sess in sessions:
        raw = cache.fetch(f"https://api.lims.leg.bc.ca/pdms/votes-and-proceedings/{sess}", ttl=6 * 3600)
        for n in json.loads(raw)["allParliamentaryFileAttributes"]["nodes"]:
            out.append({
                "session": sess,
                "file": n["fileName"].replace(".htm", ""),
                "date": n["date"][:10],
                "numbers": (n["votesAttributesByFileId"]["nodes"] or [{}])[0].get("voteNumbers"),
            })
    return sorted(out, key=lambda i: (i["date"], i["file"]))


def materialize(base, items):
    """Put each sitting's PDF and text in <base>/<session>-<file>/ (from the cache; downloads only what is missing)."""
    ok = fail = 0
    for it in items:
        d = os.path.join(base, f"{it['session']}-{it['file']}")
        os.makedirs(d, exist_ok=True)
        pdf, txt = os.path.join(d, "v.pdf"), os.path.join(d, "v.txt")
        if os.path.exists(txt) and os.path.getsize(txt) > 100:
            ok += 1
            continue
        url = f"https://lims.leg.bc.ca/pdms/ldp/{it['session']}/votes/PDF/{it['file']}.pdf"
        try:
            open(pdf, "wb").write(cache.fetch(url, binary=True, timeout=60))
            subprocess.run(["pdftotext", "-layout", pdf, txt], check=False, stderr=subprocess.DEVNULL)
            ok += 1
        except Exception as e:  # one bad sitting shouldn't stop the rest
            fail += 1
            print("FAIL", url, e)
    return ok, fail


def main(argv):
    args = list(argv)
    after = days = None
    list_only = False
    if "--after" in args:
        i = args.index("--after"); after = args[i + 1]; del args[i:i + 2]
    if "--days" in args:
        i = args.index("--days"); days = set(args[i + 1].split(",")); del args[i:i + 2]
    if "--list" in args:
        args.remove("--list"); list_only = True
    base, sessions = args[0], args[1:] or DEFAULT_SESSIONS
    os.makedirs(base, exist_ok=True)
    every = sittings(sessions)
    json.dump(every, open(os.path.join(base, "sittings.json"), "w"))
    selected = [i for i in every if (after is None or i["date"] > after) and (days is None or i["date"] in days)]
    json.dump(selected, open(os.path.join(base, "index.json"), "w"))
    if list_only:
        print(f"{len(every)} sittings listed, {len(selected)} selected; latest {every[-1]['date'] if every else '-'}")
        return
    ok, fail = materialize(base, selected)
    print(f"{len(selected)} sittings selected of {len(every)}: {ok} ready, {fail} failed")
    if fail:
        sys.exit(1)


if __name__ == "__main__":
    main(sys.argv[1:])
