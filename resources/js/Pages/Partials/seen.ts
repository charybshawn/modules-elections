import { onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// "New since your last visit", remembered per viewer in a cookie.
//
// The cookie holds, per candidate slug, the server time (Unix seconds) the
// viewer last opened that candidate's page, plus a baseline written on the
// viewer's first visit. A candidate never opened compares against the
// baseline, so a first visit doesn't badge everything already on file.
// Times always come from the server (the pages' `now` prop) and are compared
// with server timestamps, so the browser's clock never matters.
//
// One cookie per signed-in user, scoped to the module's path, a year long.
// 20-odd candidates is well under a kilobyte.

type SeenState = { base: number; c: Record<string, number> }

const MAX_AGE = 60 * 60 * 24 * 365

const cookieName = (): string => {
  const user = (usePage().props.auth as { user?: { id?: number } } | undefined)?.user
  return `elections_seen_${user?.id ?? 'guest'}`
}

const read = (name: string): SeenState | null => {
  const raw = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`))
  if (!raw) return null
  try {
    const parsed = JSON.parse(decodeURIComponent(raw.slice(name.length + 1)))
    if (typeof parsed?.base === 'number' && parsed.c && typeof parsed.c === 'object') return parsed as SeenState
  } catch {
    // A mangled cookie is treated as no cookie: start over from now.
  }
  return null
}

const write = (name: string, state: SeenState): void => {
  const secure = location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${name}=${encodeURIComponent(JSON.stringify(state))}; Path=/admin/elections; Max-Age=${MAX_AGE}; SameSite=Lax${secure}`
}

/**
 * The viewer's seen-state. Call during setup; the cookie is read on mount
 * (it doesn't exist during SSR), so `ready` stays false -- and nothing shows
 * as new -- until then. A first visit writes `now` as the baseline.
 */
export const useSeen = (now: () => number) => {
  const name = cookieName()
  const state = ref<SeenState | null>(null)

  onMounted(() => {
    state.value = read(name)
    if (!state.value) {
      state.value = { base: now(), c: {} }
      write(name, state.value)
    }
  })

  const save = (next: SeenState) => {
    state.value = next
    write(name, next)
  }

  return {
    ready: () => state.value !== null,

    /** When the viewer last opened this candidate (or the baseline); null before mount. */
    seenAt: (slug: string): number | null => (state.value ? (state.value.c[slug] ?? state.value.base) : null),

    markSeen: (slug: string): void => {
      if (state.value) save({ ...state.value, c: { ...state.value.c, [slug]: now() } })
    },

    /** Everything on file counts as seen from here on. */
    markAllSeen: (): void => save({ base: now(), c: {} }),
  }
}

/** Unix seconds for an ISO timestamp from a resource, or null. */
export const toUnix = (iso: string | null | undefined): number | null => {
  if (!iso) return null
  const ms = Date.parse(iso)
  return Number.isNaN(ms) ? null : Math.floor(ms / 1000)
}
