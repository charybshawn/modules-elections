<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Compare" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0">
      <ElectionsNav class="mb-6" />
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Compare candidates</h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Up to {{ max }} side by side: their own priorities, the subjects they share, and their scorecard answers.</p>

      <!-- Who's being compared: remove with ×, add from the list. The URL keeps the selection. -->
      <div class="mt-4 flex flex-wrap items-center gap-2">
        <span v-for="p in people" :key="p.slug" class="inline-flex items-center gap-1 rounded-full bg-gray-900 py-1 pl-3 pr-1 text-sm font-medium text-white dark:bg-white dark:text-gray-900">
          {{ p.name }}
          <button type="button" class="tap-target-touch inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-white/20 dark:hover:bg-gray-900/10" :aria-label="`Remove ${p.name}`" @click="setPicked(slugs.filter((s) => s !== p.slug))">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </span>
        <template v-if="people.length < max">
          <label for="compare-add" class="sr-only">Add a candidate</label>
          <select
            id="compare-add"
            class="rounded-full border-gray-300 py-1.5 pl-3 pr-8 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            :value="''"
            @change="addPicked(($event.target as HTMLSelectElement).value)"
          >
            <option value="" disabled>+ Add a candidate</option>
            <option v-for="c in addable" :key="c.slug" :value="c.slug">{{ c.name }}{{ c.office === 'mayor' ? ' (mayor)' : c.office === 'trustee' ? ' (trustee)' : '' }}</option>
          </select>
        </template>
      </div>

      <p v-if="people.length < 2" class="mt-6 rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">
        Pick {{ people.length ? 'one more candidate' : 'two or three candidates' }} to compare -- from the list above, or with the Compare buttons on the Elections dashboard.
      </p>

      <template v-else>
        <!-- Who they are -->
        <div :class="cols" class="mt-6 grid gap-3">
          <Link v-for="p in people" :key="p.slug" :href="route('admin.elections.candidates.show', p.slug)" class="flex min-w-0 flex-col items-center gap-2 rounded-lg bg-white p-3 text-center shadow-sm hover:ring-2 hover:ring-amber-400/60 sm:flex-row sm:text-left dark:bg-gray-800">
            <CandidatePhoto :name="p.name" :url="p.photo_url" class="h-12 w-12 shrink-0 rounded-full text-base sm:h-14 sm:w-14" />
            <div class="min-w-0">
              <div class="truncate text-sm font-medium text-gray-900 sm:text-base dark:text-white">{{ p.name }}</div>
              <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                {{ options.offices[p.office] ?? p.office }}<template v-if="p.is_incumbent"> · incumbent</template>
              </div>
              <div v-if="p.occupation" class="mt-0.5 hidden truncate text-xs text-gray-500 sm:block dark:text-gray-400">{{ p.occupation }}</div>
            </div>
          </Link>
        </div>

        <!-- Top priorities: each candidate's own top tier, in their order. Stacked on phones. -->
        <section class="mt-10">
          <h2 :class="sectionHeadingClass">Their top priorities</h2>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">What each puts front and centre in their own material, in their own order of emphasis.</p>
          <div :class="mdCols" class="mt-3 space-y-3 md:grid md:gap-3 md:space-y-0">
            <div v-for="p in people" :key="p.slug" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
              <h3 class="text-sm font-semibold text-gray-900 md:hidden dark:text-white">{{ p.name }}</h3>
              <ol v-if="topOf(p).length" class="mt-2 space-y-2 text-sm md:mt-0">
                <li v-for="(plank, i) in topOf(p)" :key="plank.key" class="flex gap-2">
                  <span class="mt-0.5 inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">{{ i + 1 }}</span>
                  <span class="text-gray-900 dark:text-gray-100">{{ plank.title }}</span>
                </li>
              </ol>
              <p v-else class="mt-2 text-sm text-gray-500 md:mt-0 dark:text-gray-400">No top-tier planks on file.</p>
              <p v-if="p.planks.length > topOf(p).length" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                + {{ p.planks.length - topOf(p).length }} more stated or mentioned ·
                <Link :href="route('admin.elections.candidates.show', { candidate: p.slug, tab: 'platform' })" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">full platform</Link>
              </p>
            </div>
          </div>
        </section>

        <!-- Subjects: shared first, then what only one raises. -->
        <section v-if="subjects.length" class="mt-10">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 :class="sectionHeadingClass">Subjects they campaign on</h2>
            <label class="tap-target-touch inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input v-model="view.sharedOnly" type="checkbox" class="rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-700" />
              Shared only
            </label>
          </div>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ sharedCount }} subject{{ sharedCount === 1 ? '' : 's' }} in common<template v-if="people.length > 2"> (raised by at least two)</template>.
          </p>

          <div class="mt-3 divide-y divide-gray-100 rounded-lg bg-white shadow-sm dark:divide-gray-700 dark:bg-gray-800">
            <div :class="rowCols" class="hidden gap-3 px-4 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid dark:text-gray-400">
              <span>Subject</span>
              <span v-for="p in people" :key="p.slug" class="truncate">{{ shortName(p.name) }}</span>
            </div>
            <TransitionGroup tag="div" enter-from-class="opacity-0" enter-active-class="transition duration-200" class="divide-y divide-gray-100 dark:divide-gray-700">
              <div v-for="s in shownSubjects" :key="s.slug" :class="rowCols" class="px-4 py-3 md:grid md:gap-3">
                <Link :href="route('admin.elections.tags.show', s.slug)" class="text-sm font-medium text-gray-900 hover:underline dark:text-white">
                  {{ s.name }}
                  <span v-if="s.shared === people.length" class="ml-1 rounded bg-green-50 px-1 py-0.5 text-xs font-normal text-green-700 dark:bg-green-500/10 dark:text-green-300">all</span>
                </Link>
                <div v-for="p in people" :key="p.slug" class="mt-1.5 flex min-w-0 items-start gap-1.5 text-xs md:mt-0">
                  <span class="w-20 shrink-0 truncate text-gray-500 md:hidden dark:text-gray-400">{{ shortName(p.name) }}</span>
                  <template v-if="s.by[p.id]">
                    <span :class="tierBadgeClass(s.by[p.id].tier)" class="shrink-0 rounded px-1.5 py-0.5 font-medium">{{ tierShortLabel[s.by[p.id].tier] ?? s.by[p.id].tier }}</span>
                    <span class="min-w-0 line-clamp-2 text-gray-600 dark:text-gray-300" :title="s.by[p.id].planks.join('; ')">{{ s.by[p.id].planks[0] }}</span>
                  </template>
                  <span v-else class="text-gray-400 dark:text-gray-500">--</span>
                </div>
              </div>
            </TransitionGroup>
          </div>
        </section>

        <!-- Scorecards: statement by statement, differences highlighted. -->
        <section v-for="sc in scorecards" :key="sc.key" class="mt-10">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 :class="sectionHeadingClass">{{ sc.title }}</h2>
            <label class="tap-target-touch inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
              <input v-model="view.differencesOnly" type="checkbox" class="rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-700" />
              Only differences
            </label>
          </div>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            <template v-if="answered(sc).length >= 2">They answer differently on {{ differCount(sc) }} of {{ itemCount(sc) }} statements.</template>
            <template v-for="p in people" :key="p.slug"><template v-if="sc.responded[p.id] !== true"> {{ p.name }} didn't answer this questionnaire -- not a position either way.</template></template>
          </p>

          <div class="mt-3 space-y-4">
            <div v-for="cat in sc.categories" v-show="shownItems(cat).length" :key="cat.key" class="rounded-lg bg-white shadow-sm dark:bg-gray-800">
              <h3 class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-white">{{ cat.name }}</h3>
              <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="item in shownItems(cat)" :key="item.key" :class="item.differs ? 'bg-amber-50/70 dark:bg-amber-500/5' : ''" class="px-4 py-2.5 md:flex md:items-center md:gap-4">
                  <p class="text-sm text-gray-800 md:flex-1 dark:text-gray-200">{{ item.statement }}</p>
                  <div class="mt-1.5 flex flex-wrap gap-1.5 md:mt-0 md:shrink-0">
                    <span
                      v-for="p in people"
                      :key="p.slug"
                      :class="stanceStyle[item.stances[p.id]]?.chip ?? stanceStyle.no_response.chip"
                      :title="`${p.name}: ${options.scorecardStances[item.stances[p.id]] ?? item.stances[p.id]}`"
                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                    >
                      <span aria-hidden="true">{{ stanceStyle[item.stances[p.id]]?.icon ?? '–' }}</span>{{ shortName(p.name) }}
                      <span class="sr-only">{{ options.scorecardStances[item.stances[p.id]] ?? item.stances[p.id] }}</span>
                    </span>
                  </div>
                </li>
              </ul>
            </div>
            <p v-if="view.differencesOnly && differCount(sc) === 0" class="rounded-lg bg-white p-4 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">No differences -- they answered every statement the same way.</p>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'
import { Link, router, useRemember } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import CandidatePhoto from './Partials/CandidatePhoto.vue'
import ElectionsNav from './Partials/ElectionsNav.vue'
import { sectionHeadingClass } from './Partials/classes'
import { tierBadgeClass, tierShortLabel } from './Partials/coverage'
import type { Candidate, Options } from './Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface ComparedPlank {
  key: string
  title: string
  summary: string | null
  tier: string
  tags: { slug: string; name: string }[]
}
type Person = Candidate & { planks: ComparedPlank[] }

interface ComparedSubject {
  slug: string
  name: string
  /** How many of the compared candidates raise it. */
  shared: number
  /** candidate id -> their strongest tier and planks on it */
  by: Record<string, { tier: string; planks: string[] }>
}

interface ComparedCategory {
  key: string
  name: string
  items: { key: string; statement: string; stances: Record<string, string>; differs: boolean }[]
}

interface ComparedScorecard {
  key: string
  title: string
  publisher: string | null
  /** candidate id -> answered (true), listed as not answering (false), or nothing on file (null) */
  responded: Record<string, boolean | null>
  categories: ComparedCategory[]
}

const props = defineProps<{
  people: Person[]
  subjects: ComparedSubject[]
  scorecards: ComparedScorecard[]
  everyone: { slug: string; name: string; office: string }[]
  max: number
  options: Options
}>()

// useRemember returns a reactive object only when given one (a plain object comes back as a ref).
const view = useRemember(reactive({ sharedOnly: false, differencesOnly: false }), 'elections-compare-view') as { sharedOnly: boolean; differencesOnly: boolean }

const slugs = computed(() => props.people.map((p) => p.slug))
const addable = computed(() => props.everyone.filter((c) => !slugs.value.includes(c.slug)))

const setPicked = (next: string[]) =>
  router.get(route('admin.elections.compare'), next.length ? { c: next } : {}, { preserveScroll: true, preserveState: true })
const addPicked = (slug: string) => {
  if (slug) setPicked([...slugs.value, slug])
}

// Static class names, so Tailwind keeps them.
const cols = computed(() => ({ 1: 'grid-cols-1', 2: 'grid-cols-2', 3: 'grid-cols-3' })[props.people.length] ?? 'grid-cols-2')
const mdCols = computed(() => ({ 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' })[props.people.length] ?? 'md:grid-cols-2')
const rowCols = computed(() => ({ 2: 'md:grid-cols-[minmax(0,14rem)_repeat(2,minmax(0,1fr))]', 3: 'md:grid-cols-[minmax(0,12rem)_repeat(3,minmax(0,1fr))]' })[props.people.length] ?? '')

/** "Kari Toliver Wilkinson" -> "Wilkinson": enough to tell columns apart. */
const shortName = (name: string) => name.split(' ').slice(-1)[0]

const topOf = (p: Person) => p.planks.filter((pl) => pl.tier === 'top')

const sharedCount = computed(() => props.subjects.filter((s) => s.shared >= 2).length)
const shownSubjects = computed(() => (view.sharedOnly ? props.subjects.filter((s) => s.shared >= 2) : props.subjects))

const answered = (sc: ComparedScorecard) => props.people.filter((p) => sc.responded[p.id] === true)
const itemCount = (sc: ComparedScorecard) => sc.categories.reduce((n, c) => n + c.items.length, 0)
const differCount = (sc: ComparedScorecard) => sc.categories.reduce((n, c) => n + c.items.filter((i) => i.differs).length, 0)
const shownItems = (cat: ComparedCategory) => (view.differencesOnly ? cat.items.filter((i) => i.differs) : cat.items)

const stanceStyle: Record<string, { icon: string; chip: string }> = {
  supportive: { icon: '✓', chip: 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200' },
  neutral: { icon: '~', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200' },
  opposed: { icon: '×', chip: 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200' },
  no_response: { icon: '–', chip: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' },
}
</script>
