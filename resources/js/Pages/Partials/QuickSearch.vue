<template>
  <!-- Trigger: a search field look-alike on desktop, an icon on phones. -->
  <button
    type="button"
    class="tap-target-touch inline-flex shrink-0 items-center gap-2 rounded-md text-sm text-gray-500 hover:text-gray-900 sm:border sm:border-gray-300 sm:bg-white sm:px-3 sm:py-1.5 sm:hover:border-gray-400 dark:text-gray-400 dark:hover:text-white sm:dark:border-gray-600 sm:dark:bg-gray-800"
    aria-haspopup="dialog"
    @click="openSearch"
  >
    <svg class="h-5 w-5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
    <span class="hidden sm:inline">Search</span>
    <kbd class="hidden rounded border border-gray-200 px-1.5 font-sans text-xs text-gray-400 sm:inline dark:border-gray-600">{{ shortcut }}</kbd>
    <span class="sr-only sm:hidden">Search candidates, subjects and planks</span>
  </button>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[70] flex items-start justify-center bg-gray-900/50 sm:p-4 sm:pt-[12vh]" @mousedown.self="closeSearch">
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Search the election"
        class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[70vh] sm:max-w-xl sm:rounded-xl dark:bg-gray-800"
        @keydown="onKeydown"
      >
        <div class="flex items-center gap-3 border-b border-gray-200 px-4 pt-[env(safe-area-inset-top)] dark:border-gray-700">
          <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
          <input
            ref="input"
            v-model="query"
            type="search"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="quick-search-results"
            :aria-activedescendant="flat[active] ? `qs-${active}` : undefined"
            aria-expanded="true"
            placeholder="Candidates, subjects, planks…"
            class="h-14 w-full border-0 bg-transparent px-0 text-base text-gray-900 placeholder-gray-400 focus:ring-0 dark:text-white"
          />
          <button type="button" class="tap-target-touch shrink-0 text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" @click="closeSearch">
            <span class="sm:hidden">Cancel</span>
            <kbd class="hidden rounded border border-gray-200 px-1.5 font-sans text-xs sm:inline dark:border-gray-600">Esc</kbd>
          </button>
        </div>

        <div id="quick-search-results" ref="list" role="listbox" class="min-h-0 flex-1 overflow-y-auto p-2">
          <p v-if="loading" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Loading…</p>
          <p v-else-if="failed" class="px-3 py-6 text-center text-sm text-red-600 dark:text-red-400">Couldn't load search. Close and try again.</p>
          <p v-else-if="query.trim() && !flat.length" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing matches "{{ query.trim() }}".</p>
          <template v-else>
            <p v-if="!query.trim()" class="px-3 pb-1 pt-2 text-xs text-gray-500 dark:text-gray-400">Type a name, a subject like "pool" or "wastewater", or words from a plank.</p>
            <section v-for="group in groups" :key="group.label" class="mt-1">
              <h3 class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ group.label }}</h3>
              <ul>
                <li
                  v-for="row in group.rows"
                  :id="`qs-${row.index}`"
                  :key="row.href"
                  role="option"
                  :aria-selected="active === row.index"
                  :class="active === row.index ? 'bg-indigo-50 dark:bg-indigo-500/15' : ''"
                  class="cursor-pointer rounded-md px-3 py-2"
                  @mousemove="active = row.index"
                  @click="go(row)"
                >
                  <div class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ row.title }}</div>
                  <div v-if="row.detail" class="truncate text-xs text-gray-500 dark:text-gray-400">{{ row.detail }}</div>
                </li>
              </ul>
            </section>
          </template>
        </div>

        <p class="hidden border-t border-gray-200 px-4 py-2 text-xs text-gray-500 sm:block dark:border-gray-700 dark:text-gray-400">
          <kbd class="font-sans">↑</kbd> <kbd class="font-sans">↓</kbd> to move · <kbd class="font-sans">Enter</kbd> to open · <kbd class="font-sans">Esc</kbd> to close
        </p>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { router } from '@inertiajs/vue3'

interface SearchIndex {
  candidates: { name: string; slug: string; office: string; occupation: string | null; status: string }[]
  subjects: { slug: string; name: string; heading: string }[]
  planks: { key: string; title: string; tier: string; candidate: string; candidate_slug: string }[]
}

interface Row {
  index: number
  title: string
  detail: string
  href: string
}

const open = ref(false)
const query = ref('')
const active = ref(0)
const loading = ref(false)
const failed = ref(false)
const input = ref<HTMLInputElement | null>(null)
const list = ref<HTMLElement | null>(null)
// shallowRef: the results recompute once the index arrives.
const index = shallowRef<SearchIndex | null>(null)
let returnFocus: HTMLElement | null = null

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)
const shortcut = isMac ? '⌘K' : 'Ctrl K'

// Loaded once, the first time the search opens.
const loadIndex = async () => {
  if (index.value || loading.value) return
  loading.value = true
  failed.value = false
  try {
    const response = await fetch(route('admin.elections.search-index'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (!response.ok) throw new Error(String(response.status))
    index.value = (await response.json()) as SearchIndex
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

const openSearch = async () => {
  returnFocus = document.activeElement as HTMLElement | null
  open.value = true
  document.body.style.overflow = 'hidden'
  await nextTick()
  input.value?.focus()
  loadIndex()
}

const closeSearch = () => {
  open.value = false
  query.value = ''
  document.body.style.overflow = ''
  returnFocus?.focus?.()
}

/**
 * Every word typed must appear somewhere; matches at the start of the
 * name, then at the start of a word, rank above ones mid-word.
 */
const score = (haystack: string, title: string, words: string[]): number => {
  const h = haystack.toLowerCase()
  if (!words.every((w) => h.includes(w))) return -1
  const t = title.toLowerCase()
  const first = words[0]
  if (t.startsWith(first)) return 3
  if (t.split(/[\s,-]+/).some((part) => part.startsWith(first))) return 2
  return 1
}

const ranked = <T,>(items: T[], text: (item: T) => string, title: (item: T) => string, words: string[], limit: number): T[] =>
  items
    .map((item) => ({ item, s: score(text(item), title(item), words) }))
    .filter((x) => x.s >= 0)
    .sort((a, b) => b.s - a.s || title(a.item).localeCompare(title(b.item)))
    .slice(0, limit)
    .map((x) => x.item)

const tierLabel: Record<string, string> = { top: 'top priority', also: 'also stated', mentioned: 'mentioned' }

const groups = computed(() => {
  const data = index.value
  if (!data) return []
  const words = query.value.toLowerCase().trim().split(/\s+/).filter(Boolean)
  let i = 0
  const row = (title: string, detail: string, href: string): Row => ({ index: i++, title, detail, href })

  // With nothing typed, offer the candidates to jump straight to.
  const candidates = words.length
    ? ranked(data.candidates, (c) => `${c.name} ${c.occupation ?? ''}`, (c) => c.name, words, 6)
    : data.candidates.filter((c) => c.status !== 'withdrawn').slice(0, 8)
  const subjects = words.length ? ranked(data.subjects, (s) => `${s.name} ${s.heading}`, (s) => s.name, words, 6) : []
  const planks = words.length ? ranked(data.planks, (p) => `${p.title} ${p.candidate}`, (p) => p.title, words, 8) : []

  return [
    {
      label: 'Candidates',
      rows: candidates.map((c) => row(c.name, [c.office === 'mayor' ? 'Mayor' : 'Council', c.occupation].filter(Boolean).join(' · '), route('admin.elections.candidates.show', c.slug))),
    },
    { label: 'Subjects', rows: subjects.map((s) => row(s.name, s.heading, route('admin.elections.tags.show', s.slug))) },
    {
      label: 'Planks',
      rows: planks.map((p) =>
        row(p.title, `${p.candidate} · ${tierLabel[p.tier] ?? p.tier}`, `${route('admin.elections.candidates.show', { candidate: p.candidate_slug, tab: 'platform' })}#plank-${p.key}`),
      ),
    },
  ].filter((g) => g.rows.length)
})

const flat = computed(() => groups.value.flatMap((g) => g.rows))

watch(query, () => {
  active.value = 0
})

const go = (row: Row) => {
  closeSearch()
  router.visit(row.href)
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeSearch()
  } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    if (!flat.value.length) return
    const step = event.key === 'ArrowDown' ? 1 : -1
    active.value = (active.value + step + flat.value.length) % flat.value.length
    nextTick(() => document.getElementById(`qs-${active.value}`)?.scrollIntoView({ block: 'nearest' }))
  } else if (event.key === 'Enter') {
    const row = flat.value[active.value]
    if (row) {
      event.preventDefault()
      go(row)
    }
  }
}

// ⌘K / Ctrl+K anywhere on the page; "/" too, when not typing in a field.
const onGlobalKeydown = (event: KeyboardEvent) => {
  const typing = event.target instanceof HTMLElement && (event.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName))
  if ((event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) || (event.key === '/' && !typing)) {
    event.preventDefault()
    if (open.value) closeSearch()
    else openSearch()
  }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
  document.body.style.overflow = ''
})
</script>
