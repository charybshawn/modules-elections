import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// "New since your last visit", for every page and badge in the module.
//
// The server sends one feed of timestamps (BuildUpdateFeed): items per
// candidate tab, plus Events, Community Pulse, Browse by subject and each City
// plan sheet. This store remembers, per viewer and per scope, when they last
// looked, and counts the feed items newer than that. Scopes:
//
//   c:<slug>:<tab>   one tab of one candidate's page (about, platform, news...)
//   events | pulse | subjects
//   plans:<slug>     one City plan sheet or scoresheet; plans:city-finances is the finances page
//
// Looking at a thing marks only that thing seen: opening a candidate on About
// does not clear their Platform tab. A scope with no mark of its own falls back
// to its parent (c:<slug>, plans), then to the baseline written on the
// viewer's first visit, so a first visit doesn't badge everything on file.
//
// Everything is kept in this browser's localStorage, one entry per signed-in
// user (the old per-candidate cookie couldn't hold per-tab marks within a
// cookie's size limit; it is read once to carry over what was already seen).
// Times always come from the server (feed.now), never the browser's clock.

export type Feed = {
  now: number
  candidates: Record<string, Record<string, number[]>>
  sections: { events: number[]; pulse: number[]; subjects: number[]; plans: Record<string, number> }
}

type Seen = { base: number; s: Record<string, number> }

const feed = ref<Feed | null>(null)
const seen = ref<Seen | null>(null)
let storageKey = ''
let loading: Promise<void> | null = null
let loadedAt = 0
let listening = false

// ---- storage ----

const readStorage = (): Seen | null => {
  try {
    const raw = window.localStorage.getItem(storageKey)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (typeof parsed?.base === 'number' && parsed.s && typeof parsed.s === 'object') return parsed as Seen
  } catch {
    // Unavailable or mangled: treated as nothing stored yet.
  }
  return null
}

const writeStorage = (value: Seen): void => {
  try {
    window.localStorage.setItem(storageKey, JSON.stringify(value))
  } catch {
    // Private mode or storage full: marks last for this page view only.
  }
}

/** What the old cookie recorded: per candidate, when they last opened them. */
const readLegacyCookie = (userKey: string): Seen | null => {
  const name = `elections_seen_${userKey}`
  const raw = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`))
  if (!raw) return null
  try {
    const parsed = JSON.parse(decodeURIComponent(raw.slice(name.length + 1)))
    if (typeof parsed?.base !== 'number' || !parsed.c || typeof parsed.c !== 'object') return null
    const s: Record<string, number> = {}
    for (const [slug, at] of Object.entries(parsed.c)) if (typeof at === 'number') s[`c:${slug}`] = at
    return { base: parsed.base, s }
  } catch {
    return null
  }
}

const save = (next: Seen): void => {
  seen.value = next
  writeStorage(next)
}

// ---- loading ----

const userKey = (): string => {
  const user = (usePage().props.auth as { user?: { id?: number } } | undefined)?.user
  return String(user?.id ?? 'guest')
}

const fetchFeed = async (): Promise<void> => {
  const response = await fetch(route('admin.elections.updates'), {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (!response.ok) return
  const next = (await response.json()) as Feed
  feed.value = next
  loadedAt = Date.now()

  if (seen.value === null) {
    // First visit on this browser: carry over the old cookie, else start from now.
    save(readLegacyCookie(userKey()) ?? { base: next.now, s: {} })
  }
}

// ---- reading ----

const parentOf = (scope: string): string | null => {
  const cut = scope.lastIndexOf(':')
  return cut > 0 ? scope.slice(0, cut) : null
}

/** When this scope was last looked at: its own mark, else its parent's, else the baseline. */
const seenAt = (scope: string): number | null => {
  const state = seen.value
  if (state === null) return null
  for (let key: string | null = scope; key !== null; key = parentOf(key)) {
    if (state.s[key] !== undefined) return state.s[key]
  }
  return state.base
}

const countNewer = (times: number[] | undefined, scope: string): number => {
  const since = seenAt(scope)
  if (since === null || !times) return 0
  return times.filter((t) => t > since).length
}

export const tabScope = (slug: string, tab: string): string => `c:${slug}:${tab}`

/** Unread items per tab of one candidate (tabs with none are left out). */
const candidateTabs = (slug: string): Record<string, number> => {
  const out: Record<string, number> = {}
  for (const [tab, times] of Object.entries(feed.value?.candidates[slug] ?? {})) {
    const n = countNewer(times, tabScope(slug, tab))
    if (n > 0) out[tab] = n
  }
  return out
}

const candidateUnread = (slug: string): number => Object.values(candidateTabs(slug)).reduce((a, b) => a + b, 0)

const sectionUnread = (section: 'events' | 'pulse' | 'subjects'): number => countNewer(feed.value?.sections[section], section)

const planUnread = (slug: string): boolean => {
  const at = feed.value?.sections.plans[slug]
  const since = seenAt(`plans:${slug}`)
  return at !== undefined && since !== null && at > since
}

/** The City finances page sits in the plans feed under its own key; it has its own tab. */
const FINANCES = 'city-finances'

const plansUnread = (): number => Object.keys(feed.value?.sections.plans ?? {}).filter((slug) => slug !== FINANCES && planUnread(slug)).length

const financesUnread = (): boolean => planUnread(FINANCES)

// ---- marking ----

/** The viewer has looked at this scope: everything in it up to now counts as seen. */
const mark = (scope: string): void => {
  if (seen.value === null || feed.value === null) return
  save({ base: seen.value.base, s: { ...seen.value.s, [scope]: feed.value.now } })
}

/** Everything on file counts as seen from here on. */
const markAll = (): void => {
  if (feed.value === null) return
  save({ base: feed.value.now, s: {} })
}

/**
 * The shared store. Call during setup; call `ensure()` from onMounted (the
 * feed and the viewer's marks only exist in the browser) before reading or
 * marking. Everything it returns is reactive.
 */
export const useUpdates = () => {
  if (!storageKey) {
    storageKey = `elections_seen_v2_${userKey()}`
    if (typeof window !== 'undefined') seen.value = readStorage()
  }
  if (typeof window !== 'undefined' && !listening) {
    listening = true
    // Another browser tab marked something seen: pick it up.
    window.addEventListener('storage', (event) => {
      if (event.key === storageKey) seen.value = readStorage()
    })
  }

  /** Load the feed (at most every 30 seconds) and the viewer's marks. */
  const ensure = (force = false): Promise<void> => {
    if (!force && feed.value !== null && Date.now() - loadedAt < 30_000) return Promise.resolve()
    loading ??= fetchFeed().finally(() => {
      loading = null
    })
    return loading
  }

  return {
    ready: computed(() => feed.value !== null && seen.value !== null),
    ensure,
    seenAt,
    mark,
    markAll,
    candidateTabs,
    candidateUnread,
    /** Candidates (by slug) with anything unread, and how many items in all. */
    candidatesUnread: () => {
      const slugs = Object.keys(feed.value?.candidates ?? {}).filter((slug) => candidateUnread(slug) > 0)
      return { candidates: slugs.length, items: slugs.reduce((n, slug) => n + candidateUnread(slug), 0) }
    },
    sectionUnread,
    planUnread,
    plansUnread,
    financesUnread,
  }
}

/** Unix seconds for an ISO timestamp from a resource, or null. */
export const toUnix = (iso: string | null | undefined): number | null => {
  if (!iso) return null
  const ms = Date.parse(iso)
  return Number.isNaN(ms) ? null : Math.floor(ms / 1000)
}
