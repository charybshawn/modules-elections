#!/usr/bin/env python3
"""A persistent, shared cache for the public legislature pages the scripts read.

    import cache
    body = cache.fetch(url)                  # a final document: cached for good
    body = cache.fetch(url, ttl=6 * 3600)    # a list that gains items: re-checked after 6 hours
    data = cache.fetch(url, binary=True)     # bytes (PDFs)

    python3 -I cache.py stats                # what is cached, and where
    python3 -I cache.py clear [--older-than DAYS]

Votes and Proceedings and final Hansard never change once published, and a bill's first-reading
text never changes, so those are cached forever; the lists that grow (sittings, progress of
bills, debates, the Members Index, the roster) carry a time-to-live so "anything new?" is one
cheap request. The cache is shared by every candidate (a second MLA of the same Parliament needs
no downloads) and survives between sessions, so unlike a scratchpad it makes a re-run free.

Location: $ELECTIONS_CACHE, else ~/.cache/cultpantry-elections/legislature. Never committed.
If the network is down and a stale copy exists, the stale copy is used (with a warning).
Requests to the Legislature are paced; cache hits are not.
"""
import atexit
import hashlib
import json
import os
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(os.environ.get("ELECTIONS_CACHE") or Path.home() / ".cache" / "cultpantry-elections" / "legislature")
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
PAUSE = 0.6  # seconds between requests that go to the network

stats = {"hits": 0, "refreshed": 0, "fetched": 0, "stale": 0}


def _paths(url: str):
    h = hashlib.sha256(url.encode()).hexdigest()
    d = ROOT / h[:2]
    return d / f"{h}.body", d / f"{h}.json"


def fetch(url: str, ttl=None, binary: bool = False, timeout: int = 90):
    """The body of `url`; from the cache unless missing or older than `ttl` seconds (None = never expires)."""
    body_path, meta_path = _paths(url)
    cached = body_path.exists() and meta_path.exists()
    age = None
    if cached:
        try:
            age = time.time() - json.loads(meta_path.read_text())["fetched_at"]
        except Exception:
            cached = False
    if cached and (ttl is None or age < ttl):
        stats["hits"] += 1
        data = body_path.read_bytes()
        return data if binary else data.decode("utf-8", "ignore")

    time.sleep(PAUSE)
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=timeout) as r:
            data = r.read()
    except Exception as e:
        if cached:
            stats["stale"] += 1
            print(f"cache: could not refresh {url} ({e}); using the copy from {int(age // 3600)}h ago", file=sys.stderr)
            data = body_path.read_bytes()
            return data if binary else data.decode("utf-8", "ignore")
        raise
    body_path.parent.mkdir(parents=True, exist_ok=True)
    body_path.write_bytes(data)
    meta_path.write_text(json.dumps({"url": url, "fetched_at": time.time(), "bytes": len(data)}))
    stats["refreshed" if cached else "fetched"] += 1
    return data if binary else data.decode("utf-8", "ignore")


def summary() -> str:
    return f"cache: {stats['hits']} from cache, {stats['fetched']} downloaded, {stats['refreshed']} re-checked" + (f", {stats['stale']} stale" if stats["stale"] else "")


def _report():
    if stats["fetched"] or stats["refreshed"] or stats["hits"]:
        print(summary(), file=sys.stderr)


atexit.register(_report)


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "stats"
    files = list(ROOT.glob("*/*.json")) if ROOT.exists() else []
    if cmd == "stats":
        total = sum(json.loads(f.read_text()).get("bytes", 0) for f in files)
        print(f"{ROOT}\n{len(files)} documents, {total / 1e6:.1f} MB")
    elif cmd == "clear":
        days = float(sys.argv[sys.argv.index("--older-than") + 1]) if "--older-than" in sys.argv else 0
        n = 0
        for f in files:
            if time.time() - json.loads(f.read_text())["fetched_at"] >= days * 86400:
                f.with_suffix(".body").unlink(missing_ok=True)
                f.unlink()
                n += 1
        print(f"removed {n} of {len(files)}")
    else:
        sys.exit("usage: cache.py stats | clear [--older-than DAYS]")
