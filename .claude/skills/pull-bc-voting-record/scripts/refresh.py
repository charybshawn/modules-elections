#!/usr/bin/env python3
"""Pull a BC MLA's voting record: new sittings first, a full backfill only the first time.

    python3 -I refresh.py status [<slug>]                  # where each candidate stands
    python3 -I refresh.py run <slug> [--full] [--since YYYY-MM-DD] [--dry-run] [--accept-flags]
    python3 -I refresh.py finish <slug> <synopses.json>    # after writing the synopses from the digest
    python3 -I refresh.py adopt <slug> --last-sitting YYYY-MM-DD   # mark a candidate backfilled already

A candidate is a folder candidates/<slug>/ with config.json (who they are in each source),
overrides.json (hand corrections to derived titles and stages), synopses.json (the written
per-bill notes) and state.json (what has been pulled). `run` does this:

  * Never run before (no state.json):  a FULL backfill of every sitting, every bill they voted on.
  * Run before:  ONE request for the list of sittings; if nothing is newer than state.last_sitting it
    stops ("nothing new"). Otherwise it reads only the new sittings, writes only the new votes, and
    prepares a digest for only the bills that gained votes or still have no synopsis. Past sittings
    are not looked at again. (--full redoes everything from the cache; --since overrides the date.)

Everything fetched goes through cache.py, so a re-run, a second candidate of the same Parliament,
and a fresh session all start warm. Work files live next to the cache, never in the repo.
Output import files go to the host app's database/elections/ (set ELECTIONS_IMPORT_DIR to change it).
"""
import collections
import datetime
import glob
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import cache  # noqa: E402
import fetch as fetch_mod  # noqa: E402

SKILL = os.path.dirname(HERE)
CANDIDATES = os.path.join(SKILL, "candidates")
WORK_ROOT = cache.ROOT.parent / "work"
PY = [sys.executable, "-I"]

READING = ("first_reading", "second_reading", "third_reading", "report_stage", "committal")


# ---------------------------------------------------------------- helpers
def die(msg, code=1):
    print(msg, file=sys.stderr)
    sys.exit(code)


def run(script, *args, quiet=False):
    r = subprocess.run(PY + [os.path.join(HERE, script), *map(str, args)], capture_output=True, text=True)
    if r.returncode != 0:
        die(f"{script} failed:\n{r.stdout}{r.stderr}")
    out = r.stdout.strip()
    if out and not quiet:
        print("  " + out.splitlines()[-1][:200])
    return out


def cand_dir(slug):
    d = os.path.join(CANDIDATES, slug)
    if not os.path.isdir(d):
        die(f"No candidate folder {d}. Create config.json there (see SKILL.md, 'Add a candidate').")
    return d


def load(path, default=None):
    return json.load(open(path)) if os.path.exists(path) else default


def save(path, data):
    json.dump(data, open(path, "w"), ensure_ascii=False, indent=1, sort_keys=False)


def import_dir():
    d = os.environ.get("ELECTIONS_IMPORT_DIR") or os.path.normpath(os.path.join(SKILL, "..", "..", "..", "..", "cultpantry", "database", "elections"))
    if not os.path.isdir(d):
        die(f"Import folder not found: {d}. Set ELECTIONS_IMPORT_DIR to the host app's database/elections folder.")
    return d


def next_name(slug, kind):
    d, today = import_dir(), datetime.date.today().isoformat()
    used = [int(m.group(1)) for f in os.listdir(d) if (m := re.match(rf"{today}-(\d+)-", f))]
    return os.path.join(d, f"{today}-{(max(used) + 1 if used else 1):02d}-bc-provincial-{kind}-{slug}.xml")


def ordinal(n):
    return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"


def next_session(code):
    m = re.match(r"^(\d+)(?:st|nd|rd|th)(\d+)(?:st|nd|rd|th)$", code)
    return f"{ordinal(int(m.group(1)))}{ordinal(int(m.group(2)) + 1)}" if m else None


def vote_files(cfg):
    return sorted(glob.glob(os.path.join(import_dir(), f"*-bc-provincial-voting-record-{cfg['slug']}*.xml")))


def onfile_votes(files):
    """(bill_key, date) for every bill vote already in an import file."""
    out = []
    for p in files:
        for v in ET.parse(p).getroot().iter("vote"):
            k = (v.findtext("bill_key") or "").strip()
            if k:
                out.append((k, (v.findtext("voted_on") or "").strip()[:10]))
    return out


def party_short(m):
    return "/".join(f"{k[0]}{v}" for k, v in sorted(m.items()))


# ---------------------------------------------------------------- flags
def flags_for(rows, overrides=()):
    """Derived rows that are probably mislabelled: a reading stage whose official wording is not that question.
    A row already corrected in overrides.json (same date, vote, tally and start of wording) is left alone."""
    def corrected(r):
        return any((o["date"], o["vote"], o.get("for"), o.get("against")) == (r["date"], r["w"], r["for"], r["against"])
                   and (r.get("text") or "").startswith(o.get("starts", "")) for o in overrides)
    bad = []
    for r in rows:
        if corrected(r):
            continue
        t = r.get("text") or ""
        stage = (r.get("stage") or "").replace(" ", "_")
        if not r.get("title"):
            bad.append((r["date"], "no title", t[:150]))
            continue
        if stage in READING:
            ok = re.search(r"(?i)(motion for (second|third) reading|introduced and read a first time|read a first time|second reading of Bill|third reading of Bill|concurred in|report stage|in consideration of (second|third) reading)", t[:400])
            moved = re.search(r"(?i)\bmoved\b[^.]{0,80}(That|,)\s", t[:250]) and not re.search(r"(?i)^(On the motion of [^,]+ that a bill|Pursuant to Standing Order 25)", t)
            if not ok or moved:
                bad.append((r["date"], f"{stage}?", t[:150]))
            elif re.search(r"(?i)debate resumed on the main motion", t[:200]):
                bad.append((r["date"], "main question after an amendment", t[:150]))
    return bad


# ---------------------------------------------------------------- digest
def digest(dossier, synopses, new_keys):
    out = []
    for r in dossier:
        b = r.get("bill") or {}
        tag = "NEW VOTES" if r["bill_key"] in new_keys else "NO NOTE YET"
        prev = synopses.get(r["bill_key"])
        out.append(f"## {r['bill_key']} — {r['bill_title']}  [{tag}{'; has an earlier note to update' if prev and tag == 'NEW VOTES' else ''}]")
        if b:
            out.append(f"sponsor: {b.get('sponsor')} ({'private member' if b.get('private_member') else 'government'}) | 1R {b.get('first_reading')} 2R {b.get('second_reading')} 3R {b.get('third_reading')} | assent {b.get('royal_assent')}")
            if b.get("explanatory_note"):
                out.append("note: " + b["explanatory_note"][:450])
        so = r.get("sponsor_opening") or r.get("intro_statement")
        if so:
            out.append("opening: " + so["text"][:650])
        elif not b:
            out.append("(no bill record and no introduction found: say its content cannot be described beyond its title)")
        for v in r["votes"]:
            c = v.get("caucus")
            cs = f" | Y:{party_short(c['yea_by_party'])} N:{party_short(c['nay_by_party'])}" if c else " | (party split unavailable)"
            out.append(f"  {v['date']} {v['stage'] or '-'} {v['vote']} {v['outcome'] or ''} {v['for']}-{v['against']}{cs} | {v['title'].split(' — ', 1)[-1][:80]}")
        sp = r.get("speeches") or []
        if sp:
            out.append(f"member spoke: {len(sp)} contribution(s) -> basis 'spoke' if it explains the vote")
            for s in sp[:3]:
                t = s["text"]
                out.append(f"  [{s['date']}] {t[:520]}" + (f" [...] {t[-380:]}" if len(t) > 900 else t[520:]))
        else:
            out.append("member did not speak -> basis 'record'; say the member's reasons are not on the record")
        if prev and tag == "NEW VOTES":
            out.append("earlier purpose: " + prev["purpose"][:300])
            out.append("earlier analysis: " + prev["vote_analysis"][:500])
        out.append("")
    return "\n".join(out)


# ---------------------------------------------------------------- run
def cmd_run(slug, args):
    d = cand_dir(slug)
    cfg = load(os.path.join(d, "config.json"))
    state = load(os.path.join(d, "state.json"), {})
    synopses = load(os.path.join(d, "synopses.json"), {})
    full = "--full" in args or not state.get("last_sitting")
    dry = "--dry-run" in args
    since = None if full else state["last_sitting"]
    if "--since" in args:
        since = args[args.index("--since") + 1]
        full = False
    first_time = not state.get("last_sitting")
    work = str(WORK_ROOT / slug)
    ctx = os.path.join(work, "ctx")
    os.makedirs(ctx, exist_ok=True)

    print(f"{cfg['name']}: " + ("FIRST RUN, full backfill" if first_time else f"incremental, after {since}") + ("  (--full)" if "--full" in args and not first_time else ""))

    # 1. what sittings exist (one cheap, cached-for-six-hours request per session)
    sessions = list(cfg["sessions"])
    nxt = next_session(sessions[-1])
    probe = []
    try:
        if nxt and fetch_mod.sittings([nxt]):
            probe = [nxt]
            print(f"  a new session is listed: {nxt}" + ("" if nxt in cfg["sessions"] else "  (add it to config.json 'sessions' with its progress_id to get bill facts)"))
    except Exception:
        pass
    sessions += probe
    every = fetch_mod.sittings(sessions)
    latest = every[-1]["date"] if every else None
    new_days = [i for i in every if since is None or i["date"] > since]
    pending = list(state.get("pending", []))
    if not new_days and not full and not pending:
        print(f"  Nothing new: {len(every)} sittings listed, latest {latest}, already pulled through {since}.")
        return
    if not new_days and not full:
        print(f"  No new sittings (latest {latest}); {len(pending)} bill(s) still need synopses from the last run.")
    else:
        print(f"  {len(new_days)} new sitting(s): {new_days[0]['date']} to {new_days[-1]['date']}")

    # 2. read the new sittings
    day_list = ",".join(i["date"] for i in new_days) if new_days else ""
    if new_days:
        run("fetch.py", work, "--days", day_list, *sessions)
    else:
        json.dump([], open(os.path.join(work, "index.json"), "w"))
        json.dump([], open(os.path.join(work, "divisions.json"), "w"))
        json.dump([], open(os.path.join(work, "described.json"), "w"))
    run("roster.py", os.path.join(ctx, "roster.json"), quiet=True)
    for s in sessions:
        pid = cfg["sessions"].get(s, {}).get("progress_id")
        if pid:
            run("bill_context.py", ctx, s, pid, "--only", "", quiet=True)  # bill titles and dates for every bill; notes come later, for the bills that need them

    new_xml = os.path.join(work, "votes-new.xml")
    new_rows = []
    if new_days:
        run("parse.py", work)
        run("describe.py", work, cfg["surname"])
        described = load(os.path.join(work, "described.json"), [])
        new_rows = [r for r in described if r["date"] > (since or "")]
        flagged = flags_for(new_rows, (load(os.path.join(d, "overrides.json"), {}) or {}).get("overrides", []))
        if flagged and "--accept-flags" not in args:
            print("\nThese derived rows look wrong; fix them in overrides.json (key them on date, vote, tally and the start of the wording) and re-run,")
            print("or re-run with --accept-flags if they are right:")
            for dte, why, t in flagged:
                print(f"  {dte}  [{why}]  {t}")
            sys.exit(2)
        run("build_xml.py", work, cfg["surname"], cfg["name"], new_xml, os.path.join(d, "overrides.json"), *(["--since", since] if since else []), quiet=True)

    new_keys = set()
    n_yea = n_nay = 0
    if os.path.exists(new_xml) and new_days:
        for v in ET.parse(new_xml).getroot().iter("vote"):
            k = (v.findtext("bill_key") or "").strip()
            if k:
                new_keys.add(k)
            n_yea += v.findtext("vote") == "yea"
            n_nay += v.findtext("vote") == "nay"
    n_new = n_yea + n_nay
    print(f"  {n_new} new vote(s) by {cfg['surname']} ({n_yea} yea, {n_nay} nay) on {len(new_keys)} bill/motion(s)" if n_new else f"  {cfg['surname']} is not listed in any recorded division in the new sitting(s)")

    # 3. which bills need a (new) synopsis: gained votes, or never had one, or left pending
    files = vote_files(cfg)
    prior = onfile_votes(files)
    prior_keys = {k for k, _ in prior}
    needs = sorted(set(new_keys) | {k for k in prior_keys if k not in synopses} | set(pending))
    all_votes = prior + [(k, "") for k in new_keys]
    dates_needed = sorted({dte for k, dte in prior if k in needs and dte})
    have_days = {i["date"] for i in json.load(open(os.path.join(work, "index.json")))}
    extra = [dte for dte in dates_needed if dte not in have_days]
    if extra:  # earlier sittings of a bill that gained votes: read only for the party split in its synopsis
        print(f"  {len(extra)} earlier sitting(s) of those bills are read for the party split")
        run("fetch.py", work, "--days", ",".join(sorted(have_days | set(extra))), *sessions, quiet=True)
    if new_days or extra:
        run("parse.py", work, quiet=True)

    # 4. the facts for those bills only
    dossier_path = os.path.join(work, "dossier.json")
    digest_path = os.path.join(work, "digest.md")
    if needs:
        refs_by_session = collections.defaultdict(list)
        for k in needs:
            s, ref = k.split(":", 1)
            refs_by_session[s].append(ref)
        for s in sessions:
            pid = cfg["sessions"].get(s, {}).get("progress_id")
            if not pid:
                continue
            mine = [r for r, b in (load(os.path.join(ctx, f"bills-{s}.json"), {}) or {}).items() if b.get("sponsor") == cfg["sponsor_name"]]
            only = ",".join(refs_by_session.get(s, []) + mine)
            run("bill_context.py", ctx, s, pid, "--only", only, quiet=True)
            if refs_by_session.get(s):
                run("sponsor_speech.py", ctx, s, "--only", ",".join(refs_by_session[s]), quiet=True)
            run("hansard.py", ctx, s, cfg["index_name"], cfg["speaker"], quiet=True)
        all_files = ",".join(files + ([new_xml] if os.path.exists(new_xml) and new_days else []))
        run("intro_statement.py", ctx, all_files, "--only", "|".join(needs), quiet=True)
        run("dossier.py", work, ctx, all_files, cfg["surname"], dossier_path, "--only", "|".join(needs), quiet=True)
        dossier = json.load(open(dossier_path))
        text = digest(dossier, synopses, set(new_keys))
        open(digest_path, "w").write(text)
        print(f"  digest for {len(dossier)} bill(s): {digest_path} ({len(text) // 1000} KB)")
    else:
        print("  No bill needs a synopsis.")

    # 5. write the import file, remember how far we got
    if dry:
        print("\n(dry run: nothing written to the import folder, state not changed)")
        return
    wrote = None
    if n_new:
        wrote = next_name(slug, "voting-record")
        shutil.move(new_xml, wrote)
        print(f"  wrote {wrote}")
    if new_days:
        state["last_sitting"] = max(i["date"] for i in new_days)
    state["backfilled_on"] = state.get("backfilled_on") or datetime.date.today().isoformat()
    state["pending"] = needs
    save(os.path.join(d, "state.json"), state)
    print(f"\nNext: write the synopses for the {len(needs)} bill(s) in the digest, then:\n  python3 -I scripts/refresh.py finish {slug} <synopses.json>" if needs else "\nDone. Import the new file with: php artisan elections:import-pending" if wrote else "\nDone.")


# ---------------------------------------------------------------- finish
def cmd_finish(slug, args):
    d = cand_dir(slug)
    cfg = load(os.path.join(d, "config.json"))
    state = load(os.path.join(d, "state.json"), {})
    work = str(WORK_ROOT / slug)
    ctx = os.path.join(work, "ctx")
    new = load(args[0])
    if new is None:
        die(f"No synopses file {args[0]}")
    dossier = load(os.path.join(work, "dossier.json"), [])
    want = {r["bill_key"] for r in dossier}
    missing = sorted(want - set(new))
    extra = sorted(set(new) - want)
    if missing or extra:
        die(f"synopses must cover exactly the digest's bills.\n  missing: {missing}\n  not in the digest: {extra}")
    synopses = load(os.path.join(d, "synopses.json"), {})
    synopses.update(new)
    save(os.path.join(d, "synopses.json"), synopses)
    tmp = os.path.join(work, "synopses-new.json")
    save(tmp, new)
    out = next_name(slug, "bill-synopses")
    run("build_notes_xml.py", os.path.join(work, "dossier.json"), tmp, cfg["name"], out)
    print(f"  wrote {out}")

    # introduced bills and motions: only when something new or changed
    sp = os.path.join(work, "sponsored.xml")
    run("sponsored.py", ctx, work, cfg["sponsor_name"], cfg["name"], sp, quiet=True)
    items = {}
    for it in ET.parse(sp).getroot().iter("item"):
        items[f"{it.findtext('kind')}|{it.findtext('session')}|{it.findtext('reference')}"] = it.findtext("status")
    seen = state.get("sponsored_seen", {})
    if any(seen.get(k) != v for k, v in items.items()):
        out2 = next_name(slug, "sponsored")
        shutil.move(sp, out2)
        print(f"  wrote {out2}")
        seen.update(items)
        state["sponsored_seen"] = seen
    state["pending"] = []
    state.setdefault("runs", []).append({"on": datetime.date.today().isoformat(), "bills_written": sorted(new)})
    save(os.path.join(d, "state.json"), state)
    print("Done. Import: php artisan elections:import-pending (back up the database first on staging).")


def cmd_status(slug=None):
    slugs = [slug] if slug else sorted(os.listdir(CANDIDATES)) if os.path.isdir(CANDIDATES) else []
    for s in slugs:
        d = os.path.join(CANDIDATES, s)
        if not os.path.isfile(os.path.join(d, "config.json")):
            continue
        st = load(os.path.join(d, "state.json"), {})
        syn = load(os.path.join(d, "synopses.json"), {})
        print(f"{s}: " + (f"pulled through {st['last_sitting']} (backfilled {st.get('backfilled_on')}), {len(syn)} synopses, {len(st.get('pending', []))} pending" if st.get("last_sitting") else "never run: the first run is a full backfill"))
    print(f"cache: {cache.ROOT}")


def main(argv):
    if not argv or argv[0] not in ("run", "finish", "status", "adopt"):
        print(__doc__)
        sys.exit(1)
    cmd, rest = argv[0], argv[1:]
    if cmd == "status":
        return cmd_status(rest[0] if rest else None)
    if not rest:
        die(f"usage: refresh.py {cmd} <slug> ...")
    slug, args = rest[0], rest[1:]
    if cmd == "run":
        return cmd_run(slug, args)
    if cmd == "finish":
        return cmd_finish(slug, args)
    d = cand_dir(slug)
    st = load(os.path.join(d, "state.json"), {})
    st["last_sitting"] = args[args.index("--last-sitting") + 1]
    st.setdefault("backfilled_on", datetime.date.today().isoformat())
    st.setdefault("pending", [])
    save(os.path.join(d, "state.json"), st)
    print(f"{slug}: marked as pulled through {st['last_sitting']}")


if __name__ == "__main__":
    main(sys.argv[1:])
