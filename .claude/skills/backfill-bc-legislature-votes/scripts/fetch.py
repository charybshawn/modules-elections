import json, os, subprocess, sys, time, urllib.request
BASE = sys.argv[1]
# sessions to fetch, e.g. 43rd1st 43rd2nd (default: both sessions of the 43rd Parliament)
SESSIONS = sys.argv[2:] or ["43rd1st", "43rd2nd"]
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
index = []
for sess in SESSIONS:
    req = urllib.request.Request(f"https://api.lims.leg.bc.ca/pdms/votes-and-proceedings/{sess}", headers={"User-Agent": UA})
    nodes = json.load(urllib.request.urlopen(req, timeout=60))["allParliamentaryFileAttributes"]["nodes"]
    for n in nodes:
        index.append({"session": sess, "file": n["fileName"].replace(".htm", ""), "date": n["date"][:10], "numbers": (n["votesAttributesByFileId"]["nodes"] or [{}])[0].get("voteNumbers")})
json.dump(index, open(os.path.join(BASE, "index.json"), "w"))
done = fail = 0
for it in index:
    d = os.path.join(BASE, f"{it['session']}-{it['file']}")
    os.makedirs(d, exist_ok=True)
    pdf = os.path.join(d, "v.pdf")
    if os.path.exists(pdf) and os.path.getsize(pdf) > 1000:
        done += 1; continue
    url = f"https://lims.leg.bc.ca/pdms/ldp/{it['session']}/votes/PDF/{it['file']}.pdf"
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        open(pdf, "wb").write(urllib.request.urlopen(req, timeout=60).read())
        subprocess.run(["pdftotext", "-layout", pdf, os.path.join(d, "v.txt")], check=False, stderr=subprocess.DEVNULL)
        done += 1
    except Exception as e:
        fail += 1; print("FAIL", url, e)
    time.sleep(1.2)
print("downloaded", done, "failed", fail, "of", len(index))
