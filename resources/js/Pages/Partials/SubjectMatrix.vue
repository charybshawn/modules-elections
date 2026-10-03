<template>
  <section>
    <!-- Filters: shared by both layouts. Chips scroll sideways on narrow screens. -->
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
      <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 scrollbar-hide sm:mx-0 sm:px-0" role="group" aria-label="Office">
        <button
          v-for="o in officeChoices"
          :key="o.value"
          type="button"
          :aria-pressed="state.office === o.value"
          :class="state.office === o.value ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
          class="tap-target-touch shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
          @click="state.office = o.value"
        >{{ o.label }}</button>
      </div>
      <label class="tap-target-touch inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input v-model="state.incumbentsOnly" type="checkbox" class="rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-700" />
        Incumbents only
      </label>
      <button
        v-if="allColumns.length > DEFAULT_COLUMNS"
        type="button"
        class="tap-target-touch hidden text-sm font-medium text-indigo-600 hover:underline md:inline-flex dark:text-indigo-400"
        @click="state.showAll = !state.showAll"
      >{{ state.showAll ? `Top ${DEFAULT_COLUMNS} subjects only` : `Show all ${allColumns.length} subjects` }}</button>
    </div>

    <ul class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400" aria-label="Legend">
      <li v-for="t in ['top', 'also', 'mentioned', '']" :key="t" class="inline-flex items-center gap-1.5">
        <span :class="tierCellClass(t)" class="inline-block h-3.5 w-3.5 rounded-sm ring-1 ring-inset ring-black/5" />{{ t ? tierShortLabel[t] : 'Nothing on file' }}
      </li>
    </ul>

    <p v-if="!rows.length" class="mt-4 rounded-lg bg-white p-4 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">No candidates match these filters.</p>

    <!-- Desktop: the full grid. Click a subject to rank candidates by it, a name or cell for details. -->
    <div v-else class="mt-4 hidden md:block">
      <div class="overflow-x-auto rounded-lg bg-white shadow-sm dark:bg-gray-800">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr>
              <th scope="col" class="sticky left-0 z-10 bg-white px-3 pb-2 text-left align-bottom text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                {{ state.sortTag ? `Ranked by ${tagName(state.sortTag)}` : 'Candidate' }}
              </th>
              <th v-for="tag in columns" :key="tag.slug" scope="col" class="px-0.5 pb-2 align-bottom">
                <button
                  type="button"
                  :aria-pressed="state.sortTag === tag.slug"
                  :title="`${tag.name}: ${tag.candidates} candidate${tag.candidates === 1 ? '' : 's'} -- click to rank by it`"
                  :class="state.sortTag === tag.slug ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                  class="mx-auto block max-h-40 py-1 text-left text-xs font-medium leading-tight [writing-mode:vertical-rl] rotate-180"
                  @click="toggleSort(tag.slug)"
                >{{ tag.name }}</button>
              </th>
              <th scope="col" class="px-3 pb-2 text-right align-bottom text-xs font-medium text-gray-500 dark:text-gray-400">Subjects</th>
            </tr>
          </thead>
          <TransitionGroup tag="tbody" move-class="transition-transform duration-300 ease-out">
            <tr
              v-for="candidate in rows"
              :key="candidate.id"
              :class="state.focus === candidate.id ? 'bg-amber-50 dark:bg-amber-500/10' : ''"
            >
              <th
                scope="row"
                :class="state.focus === candidate.id ? 'bg-amber-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800'"
                class="sticky left-0 z-10 whitespace-nowrap border-t border-gray-100 px-3 py-1.5 text-left font-normal dark:border-gray-700"
              >
                <button type="button" class="text-left font-medium text-gray-900 hover:underline dark:text-white" @click="toggleFocus(candidate.id)">{{ candidate.name }}</button>
                <span v-if="candidate.is_incumbent" class="ml-1.5 text-xs text-amber-700 dark:text-amber-400">inc.</span>
                <span v-if="candidate.office === 'mayor'" class="ml-1.5 text-xs text-gray-500 dark:text-gray-400">mayor</span>
              </th>
              <td v-for="tag in columns" :key="tag.slug" class="border-t border-gray-100 p-0.5 dark:border-gray-700" :class="state.sortTag === tag.slug ? 'bg-indigo-50/60 dark:bg-indigo-500/5' : ''">
                <button
                  type="button"
                  :aria-label="`${candidate.name}, ${tag.name}: ${cellLabel(candidate.id, tag.slug)}`"
                  :title="cellTitle(candidate.id, tag.slug)"
                  :class="[tierCellClass(cell(candidate.id, tag.slug)?.tier), isSelected(candidate.id, tag.slug) ? 'ring-2 ring-amber-500 ring-offset-1 dark:ring-offset-gray-800' : '']"
                  class="block h-7 w-full min-w-7 rounded-sm transition hover:brightness-95"
                  @click="selectCell(candidate.id, tag.slug)"
                />
              </td>
              <td class="border-t border-gray-100 px-3 text-right tabular-nums text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ subjectCount(candidate.id) }}</td>
            </tr>
          </TransitionGroup>
        </table>
      </div>

      <!-- Details for the clicked cell or name: no hover needed. -->
      <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
        <div v-if="detail" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
          <CoverageDetail :detail="detail" @close="clearDetail" />
        </div>
      </Transition>
    </div>

    <!-- Phones: pick a subject, then candidates ranked by their emphasis on it. -->
    <div v-if="rows.length" class="mt-4 md:hidden">
      <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Subject</p>
      <div class="-mx-4 mt-1.5 flex gap-1.5 overflow-x-auto px-4 pb-1 scrollbar-hide" role="group" aria-label="Subjects">
        <button
          v-for="tag in allColumns"
          :key="tag.slug"
          type="button"
          :aria-pressed="mobileTag === tag.slug"
          :class="mobileTag === tag.slug ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
          class="tap-target-touch shrink-0 rounded-full px-3 py-1.5 text-sm font-medium"
          @click="state.sortTag = tag.slug"
        >{{ tag.name }} <span class="opacity-70">{{ tag.candidates }}</span></button>
      </div>

      <TransitionGroup tag="ul" move-class="transition-transform duration-300 ease-out" class="mt-3 space-y-2">
        <li v-for="candidate in mobileRanked" :key="candidate.id" class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800">
          <div class="flex items-center justify-between gap-3">
            <Link :href="route('admin.elections.candidates.show', { candidate: candidate.slug, tab: 'platform' })" class="tap-target-touch font-medium text-gray-900 dark:text-white">{{ candidate.name }}</Link>
            <span :class="tierBadgeClass(cell(candidate.id, mobileTag)?.tier)" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium">{{ tierShortLabel[cell(candidate.id, mobileTag)?.tier ?? ''] ?? 'Nothing on file' }}</span>
          </div>
          <ul v-if="cell(candidate.id, mobileTag)" class="mt-1.5 list-disc space-y-0.5 pl-5 text-sm text-gray-600 dark:text-gray-300">
            <li v-for="p in cell(candidate.id, mobileTag)!.planks" :key="p">{{ p }}</li>
          </ul>
        </li>
      </TransitionGroup>
      <p v-if="mobileNothing.length" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
        <span class="font-medium text-gray-700 dark:text-gray-300">Nothing on file:</span> {{ mobileNothing.map((c) => c.name).join(', ') }}
      </p>
      <Link :href="route('admin.elections.tags.show', mobileTag)" class="tap-target-touch mt-2 inline-flex text-sm font-medium text-indigo-600 dark:text-indigo-400">Open {{ tagName(mobileTag) }} &rarr;</Link>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useRemember } from '@inertiajs/vue3'
import CoverageDetail from './CoverageDetail.vue'
import { cellOf, tierBadgeClass, tierCellClass, tierShortLabel, tierWeight, type CoverageDetailData, type SubjectCoverage } from './coverage'
import type { Candidate } from './types'

const props = defineProps<{
  candidates: Candidate[]
  coverage: SubjectCoverage
}>()

const DEFAULT_COLUMNS = 12

// Remembered in history state, so Back returns to the same view.
interface MatrixState {
  office: 'all' | 'mayor' | 'councillor'
  incumbentsOnly: boolean
  showAll: boolean
  sortTag: string | null
  focus: number | null
}
// useRemember hands back a reactive object for object state.
const state = useRemember<MatrixState>(
  { office: 'all', incumbentsOnly: false, showAll: false, sortTag: null, focus: null },
  'elections-subject-matrix',
) as MatrixState

const officeChoices = [
  { value: 'all', label: 'Everyone' },
  { value: 'mayor', label: 'Mayor' },
  { value: 'councillor', label: 'Council' },
] as const

const allColumns = computed(() => props.coverage.tags.filter((t) => t.candidates > 0))
const columns = computed(() => {
  const cols = state.showAll ? allColumns.value : allColumns.value.slice(0, DEFAULT_COLUMNS)
  const sorted = state.sortTag
  return sorted && !cols.some((c) => c.slug === sorted) ? [...cols, ...allColumns.value.filter((c) => c.slug === sorted)] : cols
})

const cell = (candidateId: number, slug: string) => cellOf(props.coverage, candidateId, slug)
const tagName = (slug: string | null) => props.coverage.tags.find((t) => t.slug === slug)?.name ?? ''
const subjectCount = (candidateId: number) => Object.keys(props.coverage.cells[String(candidateId)] ?? {}).length

const filtered = computed(() =>
  props.candidates.filter(
    (c) => (state.office === 'all' || c.office === state.office) && (!state.incumbentsOnly || c.is_incumbent),
  ),
)

const rankBy = (slug: string, list: Candidate[]) =>
  [...list].sort((a, b) => tierWeight(cell(a.id, slug)?.tier) - tierWeight(cell(b.id, slug)?.tier) || a.name.localeCompare(b.name))

const rows = computed(() => (state.sortTag ? rankBy(state.sortTag, filtered.value) : filtered.value))

const toggleSort = (slug: string) => {
  state.sortTag = state.sortTag === slug ? null : slug
}

// ---- Details panel (desktop) ----
const selected = ref<{ candidateId: number; slug: string } | null>(null)
const isSelected = (candidateId: number, slug: string) => selected.value?.candidateId === candidateId && selected.value.slug === slug

const selectCell = (candidateId: number, slug: string) => {
  selected.value = isSelected(candidateId, slug) ? null : { candidateId, slug }
  state.focus = selected.value ? candidateId : null
}

const toggleFocus = (candidateId: number) => {
  selected.value = null
  state.focus = state.focus === candidateId ? null : candidateId
}

const clearDetail = () => {
  selected.value = null
  state.focus = null
}

const cellLabel = (candidateId: number, slug: string) => tierShortLabel[cell(candidateId, slug)?.tier ?? ''] ?? 'nothing on file'
const cellTitle = (candidateId: number, slug: string) => {
  const c = cell(candidateId, slug)
  return c ? `${tierShortLabel[c.tier] ?? c.tier}: ${c.planks.join('; ')}` : 'Nothing on file'
}

const detail = computed<CoverageDetailData | null>(() => {
  const focus = state.focus
  const candidate = props.candidates.find((c) => c.id === focus)
  if (!candidate) return null
  if (selected.value) {
    const slug = selected.value.slug
    return { kind: 'cell', candidate, tag: { slug, name: tagName(slug) }, cell: cell(candidate.id, slug) ?? null }
  }
  const subjects = Object.entries(props.coverage.cells[String(candidate.id)] ?? {})
    .map(([slug, c]) => ({ slug, name: tagName(slug), tier: c.tier }))
    .sort((a, b) => tierWeight(a.tier) - tierWeight(b.tier) || a.name.localeCompare(b.name))
  return { kind: 'candidate', candidate, subjects }
})

// ---- Phones: one subject at a time ----
const mobileTag = computed(() => state.sortTag ?? allColumns.value[0]?.slug ?? '')
const mobileRanked = computed(() => rankBy(mobileTag.value, filtered.value).filter((c) => cell(c.id, mobileTag.value)))
const mobileNothing = computed(() => filtered.value.filter((c) => !cell(c.id, mobileTag.value)))
</script>
