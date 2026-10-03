<template>
  <div :class="compare.slugs.length ? 'pb-36 md:pb-28' : 'pb-24 md:pb-6'" class="md:pt-6">
    <AdminMobileHeader title="Elections" />

    <div class="px-4 sm:px-0">
      <ElectionsNav class="mb-6" />
      <div class="md:flex md:items-start md:justify-between gap-6 mb-6">
        <div>
          <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Salmon Arm Election 2026</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Mayor and six councillors. Every item on a candidate's page links to where it came from.
          </p>
          <p v-if="votingDay" class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">
            General voting day {{ formatDateTime(votingDay.starts_at) }}<template v-if="votingDayIn > 0"> · {{ votingDayIn }} day{{ votingDayIn === 1 ? '' : 's' }} away</template><template v-else-if="votingDayIn === 0"> · today</template>
          </p>
        </div>

        <div v-if="!readOnly" class="mt-4 md:mt-0 flex flex-wrap gap-2 shrink-0">
          <input ref="fileInput" type="file" accept=".xml,text/xml,application/xml" class="hidden" @change="handleFileChange" />
          <button
            type="button"
            :disabled="importForm.processing"
            title="Imports a research XML file. Re-importing updates what's already on file instead of duplicating it."
            :class="secondaryButtonClass"
            @click="fileInput?.click()"
          >{{ importForm.processing ? 'Importing...' : 'Import XML' }}</button>
          <!-- Plain <a>, not <Link>: Inertia would intercept the file download. -->
          <a :href="route('admin.elections.export')" :class="secondaryButtonClass">Export XML</a>
        </div>
      </div>

      <FormErrorSummary v-if="Object.keys(importForm.errors).length" :errors="importForm.errors" class="mb-6" />

      <dl class="grid grid-cols-3 gap-3 mb-8">
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Running</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.candidates }}</dd>
        </div>
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sourced items</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.entries }}</dd>
        </div>
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Articles</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.articles }}</dd>
        </div>
      </dl>

      <TagHeatMap id="subject-heat" :heat="heat" :selected="filters.tag" :matching-count="matchingCount" class="mb-6" @select="selectSubject" />

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2 space-y-8">
          <p v-if="newCandidateCount" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-amber-800 dark:text-amber-300">
            New information on {{ newCandidateCount }} candidate{{ newCandidateCount === 1 ? '' : 's' }} since your last visit.
            <button type="button" class="tap-target-touch text-xs font-medium underline underline-offset-2" @click="seen.markAllSeen()">Mark all as seen</button>
          </p>

          <!-- Find a candidate: live search and office filter, plus whatever subject is picked in the heat map. -->
          <div v-if="candidates.length" class="space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
              <label for="candidate-search" class="sr-only">Search candidates</label>
              <input
                id="candidate-search"
                v-model="filters.search"
                type="search"
                placeholder="Search by name or occupation"
                class="w-full rounded-md border-gray-300 text-base sm:max-w-xs sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
              />
              <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 scrollbar-hide sm:mx-0 sm:px-0" role="group" aria-label="Office">
                <button
                  v-for="o in officeChoices"
                  :key="o.value"
                  type="button"
                  :aria-pressed="filters.office === o.value"
                  :class="filters.office === o.value ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
                  class="tap-target-touch shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                  @click="filters.office = o.value"
                >{{ o.label }}</button>
              </div>
            </div>
          </div>

          <section v-for="group in candidateGroups" :key="group.office">
            <h2 :class="sectionHeadingClass">{{ group.label }} <span class="font-normal normal-case tracking-normal text-gray-400">{{ group.candidates.length }}</span></h2>
            <TransitionGroup tag="ul" class="relative mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" move-class="transition-transform duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-active-class="transition duration-200" leave-to-class="opacity-0 scale-95" leave-active-class="absolute transition duration-150">
              <li v-for="candidate in group.candidates" :key="candidate.id" class="relative">
                <Link
                  :href="route('admin.elections.candidates.show', candidate.slug)"
                  class="tap-target-touch flex items-center gap-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4 pr-24 hover:ring-2 hover:ring-amber-400/60 transition-opacity"
                  :class="[candidate.status === 'withdrawn' ? 'opacity-60' : '', selectedTag && !tagCell(candidate) ? 'opacity-40' : '', selectedTag && tagCell(candidate) ? 'ring-1 ring-indigo-300 dark:ring-indigo-500/50' : '']"
                >
                  <CandidatePhoto :name="candidate.name" :url="candidate.photo_url" class="h-14 w-14 shrink-0 rounded-full text-lg" />
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-gray-900 dark:text-white truncate">{{ candidate.name }}</span>
                      <span
                        v-if="newFor(candidate)"
                        class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                      >{{ newFor(candidate) }}</span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate">
                      <span v-if="candidate.is_incumbent" class="font-medium text-amber-700 dark:text-amber-400">Incumbent</span>
                      <span v-if="candidate.is_incumbent && candidate.occupation"> · </span>
                      <span>{{ candidate.occupation }}</span>
                    </div>
                    <div v-if="selectedTag && tagCell(candidate)" class="mt-1 flex items-start gap-1.5 text-xs">
                      <span :class="tierBadgeClass(tagCell(candidate)!.tier)" class="shrink-0 rounded px-1.5 py-0.5 font-medium">{{ tierShortLabel[tagCell(candidate)!.tier] ?? tagCell(candidate)!.tier }}</span>
                      <span class="min-w-0 line-clamp-2 text-gray-600 dark:text-gray-300">{{ tagCell(candidate)!.planks[0] }}</span>
                    </div>
                    <div v-else class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                      <span v-if="candidate.status !== 'nominated'">{{ options.statuses[candidate.status] ?? candidate.status }} · </span>
                      {{ candidate.entries_count ?? 0 }} item{{ candidate.entries_count === 1 ? '' : 's' }} · {{ candidate.articles_count ?? 0 }} article{{ candidate.articles_count === 1 ? '' : 's' }}
                    </div>
                  </div>
                </Link>
                <!-- Compare toggle: a sibling of the card link, so tapping it never opens the profile. -->
                <button
                  v-if="candidate.status !== 'withdrawn'"
                  type="button"
                  :aria-pressed="isPicked(candidate)"
                  :aria-label="isPicked(candidate) ? `Remove ${candidate.name} from comparison` : `Compare ${candidate.name}`"
                  :disabled="!isPicked(candidate) && compare.slugs.length >= COMPARE_MAX"
                  :class="isPicked(candidate) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50 disabled:opacity-40 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-600'"
                  class="tap-target-touch absolute right-2 top-2 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium shadow-sm ring-1 transition"
                  @click="togglePicked(candidate)"
                >
                  <svg v-if="isPicked(candidate)" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14" /></svg>
                  Compare
                </button>
              </li>
            </TransitionGroup>
          </section>

          <p v-if="candidates.length && !candidateGroups.length" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
            No candidates match these filters.
            <button type="button" class="font-medium text-indigo-600 underline underline-offset-2 dark:text-indigo-400" @click="filters.search = ''; filters.office = 'all'">Clear filters</button>
          </p>

          <p v-if="candidates.length === 0" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
            No candidates on file yet.<template v-if="!readOnly"> Import a research XML file to get started.</template>
          </p>

          <section v-if="recentArticles.length">
            <h2 :class="sectionHeadingClass">Latest coverage</h2>
            <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700 rounded-lg bg-white dark:bg-gray-800 shadow-sm">
              <li v-for="article in recentArticles" :key="article.id" class="p-4">
                <a
                  v-if="isHttpUrl(article.url)"
                  :href="article.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-gray-900 hover:underline dark:text-white"
                >{{ article.title }}</a>
                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                  {{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}
                </div>
                <div v-if="article.candidates?.length" class="mt-1 flex flex-wrap gap-1.5">
                  <Link
                    v-for="c in article.candidates"
                    :key="c.slug"
                    :href="route('admin.elections.candidates.show', c.slug)"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                  >{{ c.name }}</Link>
                </div>
              </li>
            </ul>
          </section>
        </div>

        <aside class="space-y-6">
          <section>
            <h2 :class="sectionHeadingClass">Upcoming</h2>
            <p v-if="upcomingEvents.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No upcoming events on file.</p>
            <ol v-else class="mt-3 space-y-3">
              <li v-for="event in upcomingEvents" :key="event.id" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
                <EventItem :event="event" :options="options" :read-only="readOnly" @delete="deleteEvent" />
              </li>
            </ol>
          </section>

          <section v-if="pastEvents.length">
            <details>
              <summary class="tap-target-touch cursor-pointer" :class="sectionHeadingClass">Past ({{ pastEvents.length }})</summary>
              <ol class="mt-3 space-y-3">
                <li v-for="event in pastEvents" :key="event.id" class="rounded-lg bg-white/60 dark:bg-gray-800/60 p-4">
                  <EventItem :event="event" :options="options" :read-only="readOnly" @delete="deleteEvent" />
                </li>
              </ol>
            </details>
          </section>
        </aside>
      </div>
    </div>
    <!-- Compare tray: slides up once a candidate is picked. Full-width bar on
         phones (the app's bottom-bar look), a floating pill on larger screens. -->
    <Transition enter-from-class="translate-y-full opacity-0" enter-active-class="transition duration-300 ease-out" leave-to-class="translate-y-full opacity-0" leave-active-class="transition duration-200 ease-in">
      <div
        v-if="compare.slugs.length"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[0_-4px_14px_rgba(0,0,0,0.18)] md:inset-x-auto md:bottom-4 md:left-1/2 md:w-[min(40rem,calc(100%-2rem))] md:-translate-x-1/2 md:rounded-xl md:border md:pb-3 lg:ml-32 dark:border-gray-600 dark:bg-gray-700"
        role="region"
        aria-label="Compare candidates"
      >
        <div class="flex items-center gap-3">
          <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto scrollbar-hide">
            <span v-for="c in pickedCandidates" :key="c.slug" class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-gray-100 py-0.5 pl-2.5 pr-0.5 text-sm text-gray-800 dark:bg-gray-600 dark:text-gray-100">
              {{ c.name }}
              <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-gray-500" :aria-label="`Remove ${c.name}`" @click="togglePicked(c)">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </span>
            <span v-if="compare.slugs.length < 2" class="shrink-0 self-center text-xs text-gray-500 dark:text-gray-400">Pick one more</span>
          </div>
          <button type="button" class="tap-target-touch shrink-0 text-xs font-medium text-gray-500 underline underline-offset-2 dark:text-gray-400" @click="compare.slugs = []">Clear</button>
          <Link
            :href="compareHref"
            :class="compare.slugs.length < 2 ? 'pointer-events-none opacity-50' : ''"
            :aria-disabled="compare.slugs.length < 2"
            class="tap-target-touch inline-flex shrink-0 items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
          >Compare {{ compare.slugs.length }}</Link>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import TagHeatMap from './Partials/TagHeatMap.vue'
import { cellOf, tierBadgeClass, tierShortLabel, tierWeight, type SubjectCoverage } from './Partials/coverage'
import ElectionsNav from './Partials/ElectionsNav.vue'
import { computed, nextTick, ref, reactive } from 'vue'
import { Link, router, useForm, useRemember } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import CandidatePhoto from './Partials/CandidatePhoto.vue'
import EventItem from './Partials/EventItem.vue'
import { secondaryButtonClass, sectionHeadingClass } from './Partials/classes'
import { daysUntil, formatDate, formatDateTime, isHttpUrl, useReadOnly } from './Partials/format'
import { toUnix, useSeen } from './Partials/seen'
import type { Article, Candidate, ElectionEvent, Options, TagHeat } from './Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  candidates: Candidate[]
  upcomingEvents: ElectionEvent[]
  pastEvents: ElectionEvent[]
  recentArticles: Article[]
  stats: { candidates: number; entries: number; articles: number }
  /** Unix times each candidate's entries were added and articles linked, keyed by candidate id. */
  activity: Record<number, number[]>
  /** The subject heat map. */
  heat: TagHeat
  /** Who campaigns on which subject. */
  coverage: SubjectCoverage
  /** Server time (Unix seconds). */
  now: number
  options: Options
}>()

const readOnly = useReadOnly()
const { confirmDialog } = useConfirmDialog()

// ---- Find a candidate (remembered in history state, so Back keeps it) ----
interface DashboardFilters {
  search: string
  office: 'all' | 'mayor' | 'councillor'
  tag: string | null
}
// useRemember returns a reactive object only when given one (a plain object comes back as a ref).
const filters = useRemember(reactive<DashboardFilters>({ search: '', office: 'all', tag: null }), 'elections-dashboard-filters') as DashboardFilters

const officeChoices = [
  { value: 'all', label: 'Everyone' },
  { value: 'mayor', label: 'Mayor' },
  { value: 'councillor', label: 'Council' },
] as const

/**
 * Pick a subject in the heat map: it folds to a strip, which is then
 * scrolled to the top with the candidate cards right under it.
 */
const selectSubject = async (slug: string | null) => {
  filters.tag = slug
  if (slug === null) return
  await nextTick()
  window.setTimeout(() => {
    const strip = document.getElementById('subject-heat')
    if (!strip) return
    const target = () => Math.max(0, strip.getBoundingClientRect().top + window.scrollY - stickyHeaderHeight() - 8)
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    window.scrollTo({ top: target(), behavior: reduceMotion ? 'auto' : 'smooth' })
    // Browsers skip smooth scrolling in some cases (background tabs); land it anyway.
    window.setTimeout(() => {
      if (Math.abs(window.scrollY - target()) > 48) window.scrollTo({ top: target() })
    }, 900)
  }, 50)
}

/**
 * How much of the top of the screen the sticky/fixed headers cover once
 * stuck (the admin bar, plus the page header on phones), so the strip
 * lands just below them.
 */
const stickyHeaderHeight = () =>
  Array.from(document.querySelectorAll<HTMLElement>('body *')).reduce((bottom, el) => {
    const style = getComputedStyle(el)
    if ((style.position !== 'sticky' && style.position !== 'fixed') || style.top === 'auto') return bottom
    const rect = el.getBoundingClientRect()
    const stuckTop = parseFloat(style.top) || 0
    const isHeader = rect.height > 0 && rect.width > window.innerWidth / 2 && stuckTop < window.innerHeight / 3 && rect.height < window.innerHeight / 3
    return isHeader ? Math.max(bottom, stuckTop + rect.height) : bottom
  }, 0)

// ---- Compare (up to three; remembered so Back keeps the picks) ----
const COMPARE_MAX = 3
const compare = useRemember(reactive({ slugs: [] as string[] }), 'elections-compare-picks') as { slugs: string[] }
const isPicked = (c: Candidate) => compare.slugs.includes(c.slug)
const togglePicked = (c: { slug: string }) => {
  compare.slugs = compare.slugs.includes(c.slug)
    ? compare.slugs.filter((s) => s !== c.slug)
    : compare.slugs.length < COMPARE_MAX
      ? [...compare.slugs, c.slug]
      : compare.slugs
}
const pickedCandidates = computed(() =>
  compare.slugs.map((slug) => props.candidates.find((c) => c.slug === slug)).filter((c): c is Candidate => c !== undefined),
)
const compareHref = computed(() => route('admin.elections.compare', { c: compare.slugs }))

const selectedTag = computed(() => props.coverage.tags.find((t) => t.slug === filters.tag) ?? null)
const tagCell = (candidate: Candidate) => (filters.tag ? cellOf(props.coverage, candidate.id, filters.tag) : undefined)
const matchingCount = computed(() => props.candidates.filter((c) => c.status !== 'withdrawn' && tagCell(c)).length)

const matchesSearch = (c: Candidate) => {
  const q = filters.search.trim().toLowerCase()
  return !q || c.name.toLowerCase().includes(q) || (c.occupation ?? '').toLowerCase().includes(q)
}

const candidateGroups = computed(() =>
  Object.entries(props.options.offices)
    .filter(([office]) => filters.office === 'all' || filters.office === office)
    .map(([office, label]) => {
      let list = props.candidates.filter((c) => c.office === office && matchesSearch(c))
      // A picked subject floats its candidates to the top, strongest emphasis first.
      if (filters.tag) {
        list = [...list].sort((a, b) => tierWeight(tagCell(a)?.tier) - tierWeight(tagCell(b)?.tier))
      }
      return { office, label: office === 'councillor' ? 'Council' : label, candidates: list }
    })
    .filter((group) => group.candidates.length > 0),
)

// ---- New since last visit (per-viewer cookie) ----
const seen = useSeen(() => props.now)

/** "3 new", "Updated" (profile fields only) or '' -- against when the viewer last opened them. */
const newFor = (candidate: Candidate): string => {
  const since = seen.seenAt(candidate.slug)
  if (since === null) return ''
  const count = (props.activity[candidate.id] ?? []).filter((t) => t > since).length
  if (count > 0) return `${count} new`
  return (toUnix(candidate.updated_at) ?? 0) > since ? 'Updated' : ''
}

const newCandidateCount = computed(() => props.candidates.filter((c) => newFor(c) !== '').length)

const votingDay = computed(() => props.upcomingEvents.find((e) => e.kind === 'general_voting') ?? null)
const votingDayIn = computed(() => (votingDay.value ? daysUntil(votingDay.value.starts_at) : 0))

// ---- Events ----
const deleteEvent = async (event: ElectionEvent) => {
  const confirmed = await confirmDialog({
    title: 'Delete event',
    message: `Delete "${event.title}"?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.events.destroy', event.id), { preserveScroll: true })
}

// ---- XML import ----
// A plain useForm: a File can't be persisted, and a draft selection
// wouldn't survive a reload anyway.
const importForm = useForm<{ file: File | null }>({ file: null })
const fileInput = ref<HTMLInputElement | null>(null)

const handleFileChange = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  if (!file) return

  importForm.file = file
  importForm.post(route('admin.elections.import'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => importForm.reset(),
    // Cleared either way, so picking the same file again still fires 'change'.
    onFinish: () => {
      if (fileInput.value) fileInput.value.value = ''
    },
  })
}
</script>
