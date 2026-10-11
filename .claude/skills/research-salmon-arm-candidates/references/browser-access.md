# Browser access (shared by all the Salmon Arm election skills)

Load the `browser-scrape` skill first and follow it: Browserless MCP, stealth session, one
persistent session, **one extracting call per page**, and **close the session the moment the data
is mined** (save files after it's closed). Use `browserless_agent` for anything interactive and
`browserless_smartscraper` for a single static URL. Don't use the Claude in Chrome extension.

## Fallback: Playwright (when Browserless is out of units or down)

Browserless is the default. If it returns a quota error (a 401 "units usage limit") or the MCP is
down, **don't stop and don't skip the browser step**: Playwright is installed in the host app
(`/Users/shawn/Documents/code/cultpantry/node_modules/playwright`, with Chromium cached).

```
node .claude/skills/research-salmon-arm-candidates/references/playwright-fetch.cjs [--links] [--max=6000] <url> [<url> ...]
```

- It loads each page in a real headless browser, one at a time with a pause between them, and
  prints JSON: status, title, text, `og:image`, optional `--links`, and a `challenge` flag.
- It reads **public pages only** and has no login or profile support. Facebook, LinkedIn and any
  member-only content still need Browserless with the saved profile, or are listed as not collected.
- **It does not get past bot checks.** If `challenge` is true (Cloudflare "Just a moment", a
  captcha), stop on that page and use another route: a web-archive copy (`web.archive.org/web/2026/<url>`),
  the same text on the organization's own site, or ask the user to open it. Never add stealth
  plugins or other evasion.
- Same pacing as the scrape skill: a handful of pages per run, no hammering one site.
- It is a fallback, not a replacement: Browserless still does captcha solving and the saved Facebook
  profile.

## When to use it

- **Blocked pages.** When curl or WebFetch is blocked (a 403, a Cloudflare challenge, or a page
  that's empty without JavaScript), read the page through Browserless. Never fall back on a
  search-result summary.
- **Castanet** blocks automated fetching: always read it through Browserless.
- **Facebook and LinkedIn** are only ever read through Browserless, never WebFetch.
- **Instagram: skip entirely** (the user's standing instruction, Oct. 2026). Don't open profiles
  or posts, even when an `instagram_url` is on file.

## Facebook and LinkedIn rules

- **Logged out by default.** Public Pages (a candidate's campaign page) show limited content
  before a login wall; take what's visible and report only what you actually read.
- **Content behind a login** (members-only groups such as Salmon Arm Rant and Rave, LinkedIn
  profiles) needs a saved Browserless `profile` that the user has chosen (`browserless_profiles`).
  Say which profile you're using before you use it, pass it on every call, and never log in
  yourself or type credentials. With no chosen profile, list that content as not collected and
  offer a `browserless_live_url` so the user can sign in by hand.
- **Saved profile:** the Browserless profile `facebook` holds only the user's Facebook cookies
  (uploaded with `browserless profile upload ... --only-domain facebook.com`). Use it for
  members-only groups, with a macOS persona (`os` and `emulationOs` `macos`), a Canadian
  residential sticky proxy and `humanlike`, to match where the cookies came from. After the first
  snapshot send `reportProfileAuthentication`, as its own batch.
- **Reading a group** (Salmon Arm Rant and Rave is `https://www.facebook.com/groups/714619231920570`):
  - Search with `.../search/?q=<term>`. Results sit under `[role=feed]`; each post's text is in
    `[data-ad-rendering-role="story_message"]`. The snapshot doesn't list feed items, so read them
    with `evaluate`.
  - Facebook scrambles visible text with invisible characters: strip `͏` and
    `​-‏` before reading.
  - Search results carry no permalink or date. Click the card's comment count (a `[role=button]`
    whose text is the number) to open the post; the address bar then shows the real permalink
    (`.../permalink/<id>/`), which is the `source_url`.
  - In the post, comments are `[aria-label^="Comment by"]` and `[aria-label^="Reply by"]`. Expand
    "View N replies" one at a time with varied ~4 s waits.
  - The Notifications panel opens by default as a `[role=dialog]`. Pick the dialog that holds the
    post (`story_message`) and ignore the rest; don't read or record the notifications.
- **Read-only.** Never post, react, comment, join, request to join, connect, follow, message or
  endorse. Don't open notifications or messages.
- **Keep it light.** One candidate (or profile) at a time, 8-15 seconds between loads and clicks,
  a handful of threads per sitting, at most 40 page views per session, no endless scrolling.
- **Stop at the first checkpoint:** a CAPTCHA, login wall you have no profile for, "unusual
  activity", "suspicious activity", "try again later" or a restriction notice. Stop all work on
  that site, close the session and tell the user. It may be their real account. Don't escalate
  proxies or personas.
- **Keep browser work in the main session**, not in subagents, one site at a time.
