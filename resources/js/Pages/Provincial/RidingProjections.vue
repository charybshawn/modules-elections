<template>
  <section v-if="projections.length" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
    <h2 :class="sectionHeadingClass">Riding projections <span class="font-normal normal-case tracking-normal text-gray-400">{{ projections.length }}</span></h2>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      Modelled vote shares for each riding. A projection, not a poll of the riding.
    </p>

    <!-- Filter: only the ridings a party is projected to lead. -->
    <div class="mt-3 flex flex-wrap items-center gap-1.5" role="group" aria-label="Show ridings led by">
      <span class="mr-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">Leading:</span>
      <button
        type="button"
        :aria-pressed="filter === null"
        :class="filter === null ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
        class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors"
        @click="filter = null"
      >All {{ projections.length }}</button>
      <button
        v-for="l in leaders"
        :key="l.party"
        type="button"
        :aria-pressed="filter === l.party"
        :class="filter === l.party ? [partyStyle(l.party)?.solid ?? 'bg-gray-700 text-white', 'ring-2 ring-offset-1 ring-gray-900 dark:ring-white dark:ring-offset-gray-800'] : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
        class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors"
        @click="filter = filter === l.party ? null : l.party"
      >{{ l.party }} {{ l.count }}</button>
    </div>

    <div ref="box" class="mt-3 h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700" role="region" aria-label="Riding projections" tabindex="0">
      <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No ridings match.</p>
      <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
        <li v-for="r in visible" :key="r.id" :class="r.is_local ? 'bg-amber-50 dark:bg-amber-500/10' : ''" class="px-3 py-2.5 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <span class="min-w-0 truncate font-medium text-gray-900 dark:text-white">{{ r.riding }}<span v-if="r.is_local" class="ml-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">This riding</span></span>
            <span v-if="r.rating" :class="ratingClass(r.rating)" class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold capitalize">{{ r.rating }}</span>
          </div>
          <!-- Projected shares as one stacked bar, largest first, plus the numbers. -->
          <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img" :aria-label="summary(r)">
            <span v-for="s in r.shares" :key="s.party" :class="partyStyle(s.party)?.bar ?? 'bg-gray-500'" :style="{ width: `${s.percent}%` }" :title="`${s.party} ${s.percent}%`" />
          </div>
          <div class="mt-1 flex flex-wrap gap-1">
            <span v-for="s in r.shares.filter((x) => x.percent >= 1)" :key="s.party" :class="partyStyle(s.party)?.solid ?? 'bg-gray-600 text-white'" class="rounded px-1.5 py-0.5 text-[11px] font-bold tabular-nums" :title="s.party">{{ partyStyle(s.party)?.short ?? s.party }} {{ Math.round(s.percent) }}</span>
          </div>
        </li>
      </ul>
      <div v-if="hasMore" ref="end" class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">Loading more…</div>
      <p v-else class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ filter ? `All ${filtered.length} ridings led by ${filter} shown.` : `All ${projections.length} ridings shown.` }}</p>
    </div>

    <p class="mt-2 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
      From <a v-if="source && isHttpUrl(source)" :href="source" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">338Canada</a><template v-else>338Canada</template><span v-if="asOf">, updated {{ formatDate(asOf) }}</span>.
      Its model applies province-wide and regional poll swings to each riding's last result, so local factors (incumbency, new parties) can be missed. Ratings are the model's own: safe, likely, leaning, toss up.
    </p>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { partyStyle } from './party'
import { sectionHeadingClass } from '../Partials/classes'
import { formatDate, isHttpUrl } from '../Partials/format'
import { useInfiniteScroll } from '../Partials/infiniteScroll'
import type { RidingProjectionRow } from '../Partials/types'

const props = defineProps<{ projections: RidingProjectionRow[]; source?: string | null; asOf?: string | null }>()

const filter = ref<string | null>(null)
const filtered = computed(() => (filter.value ? props.projections.filter((r) => r.leader === filter.value) : props.projections))
// One chip per party that leads somewhere, biggest first.
const leaders = computed(() => {
  const counts = new Map<string, number>()
  for (const r of props.projections) if (r.leader) counts.set(r.leader, (counts.get(r.leader) ?? 0) + 1)
  return [...counts].map(([party, count]) => ({ party, count })).sort((a, b) => b.count - a.count)
})

const { shown, box, end, visible, hasMore } = useInfiniteScroll(() => filtered.value, 15)
// A new filter starts the list over, from the top.
watch(filter, () => {
  shown.value = 15
  if (box.value) box.value.scrollTop = 0
})

const summary = (r: RidingProjectionRow) => r.shares.map((s) => `${s.party} ${s.percent} percent`).join(', ')
const ratingClass = (rating: string) =>
  ({
    safe: 'bg-slate-200 text-slate-800 dark:bg-slate-500/30 dark:text-slate-200',
    likely: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    leaning: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    'toss up': 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
  })[rating.toLowerCase()] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'

</script>
