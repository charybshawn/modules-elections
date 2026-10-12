<template>
  <div :class="showCompare && compare.slugs.length ? 'pb-36 md:pb-28' : 'pb-24 md:pb-6'" class="md:pt-6">
    <AdminMobileHeader :title="isProvincial ? election.name : 'Salmon Arm Elections'" :href="route('admin.elections.home')" />

    <div class="px-4 sm:px-0">
      <ProvincialNav :parties="partyPages" class="mb-6" />
      <div class="md:flex md:items-start md:justify-between gap-6 mb-6">
        <div>
          <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">{{ election.name }}</h1>
          <p v-if="election.riding" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ election.riding }} riding</p>
          <p v-if="votingDay" class="mt-1 text-lg font-bold text-amber-700 sm:text-xl dark:text-amber-400">
            General voting day {{ formatDateTime(votingDay.starts_at) }}<template v-if="votingDayIn > 0"> · {{ votingDayIn }} day{{ votingDayIn === 1 ? '' : 's' }} away</template><template v-else-if="votingDayIn === 0"> · today</template>
          </p>
        </div>

        <div class="mt-4 md:mt-0 flex flex-wrap gap-2 shrink-0">
          <!-- Plain <a>, not <Link>: Inertia would intercept the file download. Admins only (the policy enforces it too). -->
          <a v-if="isAdmin" :href="route('admin.elections.export', { election: election.slug })" :class="secondaryButtonClass">Export XML</a>
          <InviteButton />
        </div>
      </div>

      <!-- An amalgamation of the current province-wide polls (replaces the old count tiles). -->
      <PollPanel :data="polls" />

      <!-- Desktop: candidates take the main column; the sidebar runs Latest
           updates, the subject list, then Upcoming. Phones: updates, subjects,
           candidates, then Upcoming. -->
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 lg:grid-rows-[auto_auto_1fr]">
        <LatestUpdates :updates="latestUpdates" :now="now" scroll class="lg:col-start-3 lg:row-start-1" />

        <RidingProjections :projections="ridingProjections.rows" :source="ridingProjections.source_url" :as-of="ridingProjections.as_of" class="lg:col-start-3 lg:row-start-2" />

        <div id="candidate-list" class="min-w-0 space-y-8 lg:col-span-2 lg:col-start-1 lg:row-span-3 lg:row-start-1">
          <p v-if="newCandidateCount" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-orange-500 px-4 py-3 text-sm font-semibold text-white dark:bg-orange-600">
            New information on {{ newCandidateCount }} candidate{{ newCandidateCount === 1 ? '' : 's' }} since your last visit.
            <button type="button" class="tap-target-touch rounded bg-white/90 px-2 py-0.5 text-xs font-semibold text-orange-700 hover:bg-white" @click="updates.markAll()">Mark all as seen</button>
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
              <div class="-mx-4 -my-1 flex gap-1.5 overflow-x-auto px-4 py-1 scrollbar-hide sm:mx-0 sm:px-0" role="group" aria-label="Office">
                <button
                  v-for="o in officeChoices"
                  :key="o.value"
                  type="button"
                  :aria-pressed="filters.office === o.value"
                  :class="filters.office === o.value ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
                  class="tap-target-touch shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                  @click="filters.office = o.value"
                >{{ o.label }}</button>
                <!-- Incumbents: an on/off toggle that combines with the office choice. -->
                <span class="mx-0.5 w-px shrink-0 self-stretch bg-gray-300 dark:bg-gray-600" aria-hidden="true" />
                <button
                  type="button"
                  :aria-pressed="filters.incumbents"
                  :class="filters.incumbents ? 'bg-amber-600 text-white dark:bg-amber-500 dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
                  class="tap-target-touch shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                  @click="filters.incumbents = !filters.incumbents"
                >Incumbents</button>
              </div>
            </div>
          </div>

          <section v-for="group in candidateGroups" :key="group.office">
            <h2 :class="sectionHeadingClass">{{ group.label }} <span class="font-normal normal-case tracking-normal text-gray-400">{{ group.candidates.length }}</span></h2>
            <TransitionGroup tag="ul" class="relative mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" move-class="transition-transform duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-active-class="transition duration-200" leave-to-class="opacity-0 scale-95" leave-active-class="absolute transition duration-150">
              <!-- The card is the <li>; the name's link is stretched over it, and the party link sits above that, so neither nests in the other. -->
              <li
                v-for="candidate in group.candidates"
                :key="candidate.id"
                class="tap-target-touch relative flex h-full items-center gap-4 overflow-hidden rounded-lg shadow-sm p-4 pr-24 hover:ring-2 hover:ring-amber-400/60 transition-opacity"
                :class="[
                  // Anything unread tints the whole card orange; the tabs that changed are named inside.
                  newFor(candidate) ? 'bg-orange-50 ring-2 ring-orange-400 dark:bg-orange-500/10 dark:ring-orange-500/70' : 'bg-white dark:bg-gray-800',
                  candidate.status === 'withdrawn' ? 'opacity-60' : '',
                  selectedTag && !tagCell(candidate) ? 'opacity-40' : '',
                  selectedTag && tagCell(candidate) ? 'ring-1 ring-indigo-300 dark:ring-indigo-500/50' : '',
                ]"
              >
                <!-- A stripe of the party's colour down the card's left edge, inside the card so it can't outgrow it. -->
                <span v-if="partyStyle(candidate.party)" :class="partyStyle(candidate.party)!.bar" class="pointer-events-none absolute inset-y-0 left-0 w-2" aria-hidden="true" />
                <PartyPhoto :name="candidate.name" :url="candidate.photo_url" :party="candidate.party" photo-class="h-28 w-28 shrink-0 rounded-full text-3xl sm:h-32 sm:w-32" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <Link :href="route('admin.elections.candidates.show', candidate.slug)" class="truncate font-medium text-gray-900 after:absolute after:inset-0 after:content-[''] dark:text-white">{{ candidate.name }}</Link>
                    <span
                      v-if="newFor(candidate)"
                      class="shrink-0 rounded-full bg-orange-500 px-2 py-0.5 text-xs font-semibold text-white dark:bg-orange-600"
                    >{{ newFor(candidate) }}</span>
                  </div>
                  <!-- The party, loud: it's what voters sort candidates by in a provincial race. -->
                  <div v-if="partyStyle(candidate.party)" class="mt-1">
                    <span :class="partyStyle(candidate.party)!.solid" class="inline-block max-w-full truncate rounded-md px-2.5 py-1 align-middle text-sm font-extrabold uppercase leading-none tracking-wide shadow-sm">{{ candidate.party }}</span>
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400 truncate" :class="partyStyle(candidate.party) ? 'mt-1' : ''">
                    <span v-if="candidate.is_incumbent" class="font-medium text-amber-700 dark:text-amber-400">Incumbent</span>
                    <span v-if="candidate.is_incumbent && candidate.occupation"> · </span>
                    <span>{{ candidate.occupation }}</span>
                  </div>
                  <div v-if="newTabsFor(candidate)" class="text-xs font-medium text-orange-700 dark:text-orange-300 truncate">New in: {{ newTabsFor(candidate) }}</div>
                  <div v-if="selectedTag && tagCell(candidate)" class="mt-1 flex items-start gap-1.5 text-xs">
                    <span :class="tierBadgeClass(tagCell(candidate)!.tier)" class="shrink-0 rounded px-1.5 py-0.5 font-medium">{{ tierShortLabel[tagCell(candidate)!.tier] ?? tagCell(candidate)!.tier }}</span>
                    <span class="min-w-0 line-clamp-2 text-gray-600 dark:text-gray-300">{{ tagCell(candidate)!.planks[0] }}</span>
                  </div>
                  <div v-else class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                    <span v-if="candidate.status !== 'nominated'">{{ options.statuses[candidate.status] ?? candidate.status }} · </span>
                    {{ candidate.entries_count ?? 0 }} item{{ candidate.entries_count === 1 ? '' : 's' }} · {{ candidate.articles_count ?? 0 }} article{{ candidate.articles_count === 1 ? '' : 's' }}
                  </div>
                  <!-- The party's own page, inside the card and above the stretched card link. -->
                  <Link
                    v-if="candidate.party && partyPages?.[candidate.party]"
                    :href="route('admin.elections.provincial.parties.show', partyPages[candidate.party])"
                    class="relative z-10 mt-1.5 inline-flex max-w-full items-center gap-1 truncate text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                  >{{ candidate.party }}: news and fact-check <span aria-hidden="true">→</span></Link>
                </div>
                <!-- Compare toggle: a sibling of the card link, so tapping it never opens the profile. -->
                <button
                  v-if="showCompare && candidate.status !== 'withdrawn'"
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
            <button type="button" class="font-medium text-indigo-600 underline underline-offset-2 dark:text-indigo-400" @click="filters.search = ''; filters.office = 'all'; filters.incumbents = false">Clear filters</button>
          </p>

          <p v-if="candidates.length === 0" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
            No candidates on file yet.
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
                <!-- Who it covers: folded to one line, opened on demand (round-ups name everyone). -->
                <div v-if="article.candidates?.length" class="mt-1">
                  <Link
                    v-if="article.candidates.length === 1"
                    :href="route('admin.elections.candidates.show', article.candidates[0].slug)"
                    class="text-xs text-gray-600 hover:underline dark:text-gray-300"
                  >{{ article.candidates[0].name }}</Link>
                  <template v-else>
                    <button
                      type="button"
                      :aria-expanded="openArticles.has(article.id)"
                      class="tap-target-touch inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                      @click="toggleArticle(article.id)"
                    >
                      Mentions {{ article.candidates.length }} candidates
                      <svg :class="openArticles.has(article.id) ? 'rotate-180' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-if="openArticles.has(article.id)" class="mt-1.5 flex flex-wrap gap-1.5">
                      <Link
                        v-for="c in article.candidates"
                        :key="c.slug"
                        :href="route('admin.elections.candidates.show', c.slug)"
                        class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                      >{{ c.name }}</Link>
                    </div>
                  </template>
                </div>
              </li>
            </ul>
          </section>
        </div>

        <aside class="space-y-6 lg:col-start-3 lg:row-start-3">
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
        v-if="showCompare && compare.slugs.length"
        class="fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-40 border-t border-gray-200 bg-white px-4 py-3 shadow-[0_-4px_14px_rgba(0,0,0,0.18)] md:inset-x-auto md:bottom-4 md:left-1/2 md:w-[min(40rem,calc(100%-2rem))] md:-translate-x-1/2 md:rounded-xl md:border md:pb-3 lg:ml-32 dark:border-gray-600 dark:bg-gray-700"
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
import LatestUpdates from '../Partials/LatestUpdates.vue'
import PollPanel from './PollPanel.vue'
import RidingProjections from './RidingProjections.vue'
import { cellOf, tierBadgeClass, tierShortLabel, tierWeight, type SubjectCoverage } from '../Partials/coverage'
import ProvincialNav from './ProvincialNav.vue'
import InviteButton from '../Partials/InviteButton.vue'
import { computed, nextTick, onMounted, ref, reactive } from 'vue'
import { Link, router, usePage, useRemember } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import PartyPhoto from './PartyPhoto.vue'
import { partyStyle } from './party'
import EventItem from '../Partials/EventItem.vue'
import { secondaryButtonClass, sectionHeadingClass } from '../Partials/classes'
import { daysUntil, formatDate, formatDateTime, isHttpUrl, useReadOnly } from '../Partials/format'
import { useUpdates } from '../Partials/updates'
import type { Article, Candidate, ElectionEvent, ElectionInfo, Options, TagHeat, LatestUpdate, PollData, RidingProjectionRow } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  candidates: Candidate[]
  upcomingEvents: ElectionEvent[]
  pastEvents: ElectionEvent[]
  recentArticles: Article[]
  polls: PollData
  ridingProjections: { rows: RidingProjectionRow[]; source_url: string | null; as_of: string | null }
  /** The subject heat map. */
  heat: TagHeat
  /** Who campaigns on which subject. */
  coverage: SubjectCoverage
  /** Newest additions, grouped per candidate per day, plus news stories. */
  latestUpdates: LatestUpdate[]
  /** Server time (Unix seconds). */
  now: number
  options: Options
  /** Which election this dashboard is for. */
  election: ElectionInfo
  /** Provincial only: each party's name -> the address of its politics page. */
  partyPages?: Record<string, string>
}>()

// The provincial dashboard has no Compare or subject heat map.
const isProvincial = true
const showCompare = false

const readOnly = useReadOnly()
// The XML export is the whole dataset: admins only (CandidatePolicy::export).
const isAdmin = computed(() => ['admin', 'super_admin'].includes((usePage().props.auth as { user?: { role?: string } } | undefined)?.user?.role ?? ''))
const { confirmDialog } = useConfirmDialog()

// ---- Find a candidate (remembered in history state, so Back keeps it) ----
interface DashboardFilters {
  search: string
  office: string
  incumbents: boolean
  tag: string | null
}
// useRemember returns a reactive object only when given one (a plain object comes back as a ref).
const filters = useRemember(reactive<DashboardFilters>({ search: '', office: 'all', incumbents: false, tag: null }), `elections-dashboard-filters-${props.election.slug}`) as DashboardFilters

// Short button labels and group headings; an office without one uses its full label.
const officeShort: Record<string, string> = { councillor: 'Council', trustee: 'School board' }
const officeHeading: Record<string, string> = { councillor: 'Council', trustee: 'School board trustees' }

const officeChoices = computed(() => [
  { value: 'all', label: 'Everyone' },
  ...Object.entries(props.election.offices).map(([value, label]) => ({ value, label: officeShort[value] ?? label })),
])

/**
 * Pick a subject: its candidates float to the top of the list. On phones,
 * where the subject list sits above the cards, scroll the cards into view
 * (just below the sticky headers); on desktop they're already beside it.
 */
const selectSubject = async (slug: string | null) => {
  filters.tag = slug
  if (slug === null || window.matchMedia('(min-width: 1024px)').matches) return
  await nextTick()
  window.setTimeout(() => {
    const cards = document.getElementById('candidate-list')
    if (!cards) return
    const target = () => Math.max(0, cards.getBoundingClientRect().top + window.scrollY - stickyHeaderHeight() - 8)
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

// ---- Latest coverage: which stories have their candidate list open ----
const openArticles = ref(new Set<number>())
const toggleArticle = (id: number) => {
  const next = new Set(openArticles.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  openArticles.value = next
}

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
  Object.entries(props.election.offices)
    .filter(([office]) => filters.office === 'all' || filters.office === office)
    .map(([office, label]) => {
      let list = props.candidates.filter((c) => c.office === office && matchesSearch(c) && (!filters.incumbents || c.is_incumbent))
      // A picked subject floats its candidates to the top, strongest emphasis first.
      if (filters.tag) {
        list = [...list].sort((a, b) => tierWeight(tagCell(a)?.tier) - tierWeight(tagCell(b)?.tier))
      }
      return { office, label: officeHeading[office] ?? label, candidates: list }
    })
    .filter((group) => group.candidates.length > 0),
)

// ---- New since last visit (per viewer, per tab) ----
// Viewing the dashboard marks the events list seen; each candidate's own tabs
// stay flagged until you open them (see Partials/updates.ts).
const updates = useUpdates(props.election.slug)
onMounted(async () => {
  await updates.ensure()
  updates.mark('events')
})

const tabTitles: Record<string, string> = {
  about: 'About', affiliations: 'Background', platform: 'Platform', scorecards: 'Scorecard', words: 'Statements',
  record: 'Voting record', endorsements: 'Endorsements', finance: 'Finance', news: 'News', notes: 'Notes',
}

/** "3 new", or '' when nothing is unread for them. */
const newFor = (candidate: Candidate): string => {
  const n = updates.candidateUnread(candidate.slug)
  return n > 0 ? `${n} new` : ''
}
/** The tabs on their page with something new, e.g. "Platform, News". */
const newTabsFor = (candidate: Candidate): string =>
  Object.keys(updates.candidateTabs(candidate.slug)).map((tab) => tabTitles[tab] ?? tab).join(', ')

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
</script>
