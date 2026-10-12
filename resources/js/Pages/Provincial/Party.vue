<template>
  <AdminShowShell wide>
    <template #mobile-header>
      <AdminMobileHeader :title="party.name" :href="dashboardHref" />
    </template>

    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="dashboardHref" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">
          &larr; Back to Elections
        </Link>
        <QuickSearch :election="election.slug" />
      </div>
      <h1 class="hidden md:block mt-2 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">{{ party.full_name ?? party.name }}</h1>
    </template>

    <div class="space-y-8 md:mt-6">
      <!-- Identity and key facts: the party in its colours, who leads it, who is running for it here. -->
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span v-if="style" :class="style.solid" class="rounded-md px-3 py-1 text-sm font-extrabold uppercase tracking-wide shadow-sm">{{ party.name }}</span>
          <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">Provincial party</span>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div v-if="party.leader" class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Current leader</dt>
            <dd class="text-gray-900 dark:text-white">{{ party.leader }}</dd>
          </div>
          <div v-if="candidates.length" class="min-w-0">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Its candidate in {{ election.riding ?? 'this riding' }}</dt>
            <dd class="text-gray-900 dark:text-white">
              <template v-for="(c, i) in candidates" :key="c.slug">
                <span v-if="i" aria-hidden="true">, </span>
                <Link :href="route('admin.elections.candidates.show', c.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ c.name }}</Link>
              </template>
            </dd>
          </div>
        </dl>
      </div>

      <!-- Tabs: each is its own URL (?tab=), like the candidate page. Underline tabs from md up; a native picker on phones. -->
      <div>
        <div class="md:hidden">
          <label for="party-tab" class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sections</label>
          <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <select
              id="party-tab"
              :value="activeTab"
              class="block w-full appearance-none bg-none rounded-lg border-2 py-3 pl-11 pr-10 text-base font-semibold shadow-sm border-indigo-500 bg-indigo-50 text-indigo-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-indigo-400 dark:bg-indigo-900/40 dark:text-white"
              @change="switchTab(($event.target as HTMLSelectElement).value)"
            >
              <option v-for="tab in tabs" :key="tab.id" :value="tab.id">{{ tab.title }}{{ tab.count ? ` (${tab.count})` : '' }}</option>
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
            </svg>
          </div>
        </div>

        <nav class="hidden md:flex gap-1 overflow-x-auto scrollbar-hide border-b border-gray-200 dark:border-gray-700" aria-label="Party sections">
          <Link
            v-for="tab in tabs"
            :key="tab.id"
            :href="tabHref(tab.id)"
            preserve-state
            preserve-scroll
            :aria-current="activeTab === tab.id ? 'page' : undefined"
            :class="[
              'tap-target-touch -mb-px inline-flex items-center gap-1.5 rounded-t-md py-3 px-3 border-b-2 font-medium text-sm whitespace-nowrap transition-colors',
              activeTab === tab.id
                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
            ]"
          >
            {{ tab.title }}
            <span v-if="tab.count" class="text-xs font-normal text-gray-400 dark:text-gray-500">{{ tab.count }}</span>
          </Link>
        </nav>

        <!-- About the party: the shared profile block. -->
        <!-- Two columns from large screens up: the profile on the left, the province-wide candidates on the right. -->
        <div v-if="activeTab === 'about'" class="grid grid-cols-1 items-start gap-x-10 lg:grid-cols-2">
          <AccountSection title="About the party" description="Who leads it, how long it has existed, its record in government and what it stands for. Dated, and re-checked as the news changes.">
            <PartyProfile :profile="party" />
          </AccountSection>

          <!-- Every candidate the party is running across the province: a fixed-height list that loads more as it is scrolled. -->
          <AccountSection
            v-if="slate.candidates.length"
            :title="`Candidates across the province (${slate.candidates.length})`"
            :description="`Who the party is running in each riding, from Elections BC's final candidate list${incumbentCount ? `; ${incumbentCount} are sitting MLAs running for re-election` : ''}. Only this riding's candidate has a page here.`"
          >
            <div
              ref="slateBox"
              class="h-96 overflow-y-auto rounded-lg lg:h-[32rem] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
              role="region"
              :aria-label="`${party.name} candidates by riding`"
              tabindex="0"
            >
              <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <li v-for="c in visibleSlate" :key="c.id" :class="isLocal(c) ? 'bg-amber-50 dark:bg-amber-500/10' : ''" class="flex items-start justify-between gap-4 px-4 py-2.5 text-sm">
                  <span class="min-w-0 truncate pt-px text-gray-500 dark:text-gray-400">{{ c.riding }}</span>
                  <span class="flex min-w-0 shrink-0 flex-col items-end text-right">
                    <Link v-if="isLocal(c)" :href="route('admin.elections.candidates.show', slate.local[c.name])" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ c.name }}</Link>
                    <span v-else class="font-medium text-gray-900 dark:text-white">{{ c.name }}</span>
                    <!-- A sitting MLA running again; a different seat is named. -->
                    <span v-if="c.incumbent_riding" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                      <span class="rounded bg-amber-100 px-1.5 py-0.5 uppercase tracking-wide dark:bg-amber-500/20">Incumbent</span>
                      <span v-if="c.incumbent_riding !== c.riding" class="font-normal text-gray-500 dark:text-gray-400">MLA for {{ c.incumbent_riding }}</span>
                    </span>
                  </span>
                </li>
              </ul>
              <!-- The sentinel: when it scrolls into view, the next batch is added. -->
              <div v-if="shown < slate.candidates.length" ref="slateEnd" class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">Loading more…</div>
              <p v-else class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">All {{ slate.candidates.length }} candidates shown.</p>
            </div>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
              <span v-if="slate.as_of">Checked {{ formatDate(slate.as_of) }}. </span>
              <a v-if="slate.source_url && isHttpUrl(slate.source_url)" :href="slate.source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">Elections BC candidate list</a>
            </p>
          </AccountSection>
        </div>

        <!-- News about the party as a whole, a column for each type of story. Stories about one candidate stay on that candidate's page. -->
        <div v-else-if="activeTab === 'news'">
          <AccountSection title="Coverage" description="News stories about the party as a whole, in a column for each type of story. Stories about one candidate are on that candidate's page.">
            <p v-if="!articles.length" class="text-sm text-gray-500 dark:text-gray-400">No stories about the party as a whole are on file yet.</p>
            <div v-else class="grid grid-cols-1 items-start gap-4" :class="newsColumnsClass">
              <section v-for="section in newsSections" :key="section.type" class="min-w-0 rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-800/40">
                <h3 class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                  <span :class="newsTagClass(section.type)" class="rounded px-2 py-0.5 text-xs font-bold uppercase tracking-wide">{{ section.name }}</span>
                  <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ section.articles.length }} {{ section.articles.length === 1 ? 'story' : 'stories' }}</span>
                </h3>
                <ul class="mt-3 space-y-2 lg:max-h-[70vh] lg:overflow-y-auto lg:pr-1">
                  <li v-for="a in section.articles" :key="a.id" class="rounded-md bg-white p-2.5 shadow-sm dark:bg-gray-800">
                    <a v-if="isHttpUrl(a.url)" :href="a.url" target="_blank" rel="noopener noreferrer" class="text-sm font-medium leading-snug text-gray-900 hover:underline dark:text-white">{{ a.title }}</a>
                    <span v-else class="text-sm font-medium leading-snug text-gray-900 dark:text-white">{{ a.title }}</span>
                    <p v-if="a.summary" class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ a.summary }}</p>
                    <p class="mt-1.5 truncate text-[11px] text-gray-500 dark:text-gray-400">{{ [a.outlet, formatDate(a.published_on)].filter(Boolean).join(' · ') }}</p>
                  </li>
                </ul>
              </section>
            </div>
          </AccountSection>
        </div>

        <!-- Fact-check: what the party says, set against what its record and votes show. -->
        <div v-else-if="activeTab === 'fact-check'" class="divide-y divide-gray-200 dark:divide-gray-700">
          <AccountSection title="How to read this" description="Each check sets something the party says against the record.">
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">Each check takes a claim or a pledge and sets it against how its MLAs voted in the Legislature, what it did in government, its own past statements and independent reporting. The rating says how far that record supports it. Each check is dated and cites its sources; a rating can change as the record does.</p>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
              <li v-for="(label, key) in options.ratings" :key="key" class="flex items-center gap-1.5">
                <span :class="ratingClass(key)" class="rounded px-1.5 py-0.5 text-xs font-semibold">{{ label }}</span>
                <span v-if="ratingCounts[key]" class="text-xs text-gray-500 dark:text-gray-400">{{ ratingCounts[key] }}</span>
              </li>
            </ul>
          </AccountSection>

          <AccountSection title="Checks" :description="claims.length ? `${claims.length} headline claims and pledges.` : undefined">
            <p v-if="!claims.length" class="text-sm text-gray-500 dark:text-gray-400">No claims have been checked for this party yet.</p>
            <ol v-else class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
              <li v-for="c in claims" :key="c.id" class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center gap-2">
                  <span :class="ratingClass(c.rating)" class="rounded px-2 py-0.5 text-xs font-bold">{{ options.ratings[c.rating] ?? c.rating }}</span>
                  <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ options.claimKinds[c.kind] ?? c.kind }}</span>
                  <span v-if="c.topic" class="text-xs text-gray-500 dark:text-gray-400">{{ c.topic }}</span>
                </div>
                <blockquote class="mt-2 border-l-4 border-gray-200 pl-3 text-sm font-medium leading-snug text-gray-900 dark:border-gray-600 dark:text-white">
                  {{ c.claim }}
                  <footer class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                    Said at <a v-if="isHttpUrl(c.source_url)" :href="c.source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">{{ c.source_name ?? hostOf(c.source_url) }}</a>
                  </footer>
                </blockquote>
                <p class="mt-3 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ c.summary }}</p>
                <ul v-if="c.evidence.length" class="mt-3 space-y-2 border-t border-gray-100 pt-3 dark:border-gray-700/60">
                  <li v-for="(e, i) in c.evidence" :key="i" class="text-sm">
                    <span class="mr-1.5 rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ options.evidenceKinds[e.kind] ?? e.kind }}</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ e.text }}</span>
                    <a v-if="e.url && isHttpUrl(e.url)" :href="e.url" target="_blank" rel="noopener noreferrer" class="ml-1.5 text-xs text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">{{ e.label ?? hostOf(e.url) }}</a>
                  </li>
                </ul>
                <p v-if="c.checked_on" class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">Checked {{ formatDate(c.checked_on) }}.</p>
              </li>
            </ol>
          </AccountSection>
        </div>
      </div>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import AccountSection from '@/Components/Admin/Accounts/AccountSection.vue'
import PartyProfile from './PartyProfile.vue'
import { partyStyle } from './party'
import { newsTagClass } from './newsTags'
import QuickSearch from '../Partials/QuickSearch.vue'
import { formatDate, hostOf, isHttpUrl } from '../Partials/format'
import type { ElectionInfo, PartyArticle, PartyClaim, PartyProfile as PartyProfileData, PartySlate } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  election: ElectionInfo
  party: PartyProfileData
  candidates: { name: string; slug: string; photo_url: string | null; party: string | null; is_incumbent: boolean; occupation: string | null }[]
  claims: PartyClaim[]
  ratingCounts: Record<string, number>
  articles: PartyArticle[]
  options: { ratings: Record<string, string>; claimKinds: Record<string, string>; evidenceKinds: Record<string, string>; newsTypes: Record<string, string> }
  slate: PartySlate
  activeTab: string
}>()

const dashboardHref = route('admin.elections.provincial')
const style = computed(() => partyStyle(props.party.name))

const tabs = computed(() => [
  { id: 'about', title: 'About', count: 0 },
  { id: 'news', title: 'In the news', count: props.articles.length },
  { id: 'fact-check', title: 'Fact-check', count: props.claims.length },
])

const tabHref = (id: string): string => {
  const params: Record<string, string> = { party: props.party.slug as string }
  if (id !== 'about') params.tab = id
  return route('admin.elections.provincial.parties.show', params)
}
const switchTab = (id: string) => {
  if (id !== props.activeTab) router.get(tabHref(id), {}, { preserveState: true, preserveScroll: true })
}

// News: one column per type of story, in the candidate page's order.
const newsSections = computed(() =>
  ['legislative', 'opinion', 'background']
    .map((type) => ({ type, name: props.options.newsTypes[type] ?? type, articles: props.articles.filter((a) => a.type === type) }))
    .filter((s) => s.articles.length),
)
const newsColumnsClass = computed(
  () => (({ 1: 'lg:grid-cols-1', 2: 'lg:grid-cols-2' }) as Record<number, string>)[newsSections.value.length] ?? 'lg:grid-cols-3',
)

// ---- Province-wide candidates: shown a batch at a time as the box is scrolled ----
const SLATE_STEP = 20
const shown = ref(SLATE_STEP)
const slateBox = ref<HTMLElement | null>(null)
const slateEnd = ref<HTMLElement | null>(null)
const incumbentCount = computed(() => props.slate.candidates.filter((c) => c.incumbent_riding).length)
const visibleSlate = computed(() => props.slate.candidates.slice(0, shown.value))
const isLocal = (c: { riding: string; name: string }) => c.riding === props.slate.local_riding && !!props.slate.local[c.name]

let observer: IntersectionObserver | null = null
const watchEnd = () => {
  observer?.disconnect()
  if (!slateBox.value || !slateEnd.value) return
  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting) && shown.value < props.slate.candidates.length) shown.value += SLATE_STEP
    },
    { root: slateBox.value, rootMargin: '0px 0px 80px 0px' },
  )
  observer.observe(slateEnd.value)
}
onMounted(() => nextTick(watchEnd))
// The sentinel is re-created as batches load (and on tab switches), so watch it again each time.
watch([shown, () => props.activeTab], () => nextTick(watchEnd))
onBeforeUnmount(() => observer?.disconnect())

/** Colour for a rating: green where the record backs the claim, red where it doesn't, grey where it can't be tested. */
const ratingClass = (rating: string) =>
  ({
    holds_up: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    mostly_holds_up: 'bg-lime-100 text-lime-800 dark:bg-lime-500/20 dark:text-lime-300',
    mixed: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    mostly_does_not: 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300',
    does_not_hold_up: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
    untestable: 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
  })[rating] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
</script>
