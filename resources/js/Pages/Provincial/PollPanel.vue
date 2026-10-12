<template>
  <section class="mb-8 rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h2 :class="sectionHeadingClass">Province-wide polls</h2>
      <p class="flex flex-wrap items-baseline gap-x-3 text-xs text-gray-500 dark:text-gray-400">
        <span v-if="data.average">Average of {{ data.average.polls }} current polls · {{ formatDate(data.average.from) }} to {{ formatDate(data.average.to) }}</span>
        <!-- The history chart: every poll over time, with a trend line per party. -->
        <Link v-if="data.polls.length" :href="route('admin.elections.provincial.polls')" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Poll history and trends →</Link>
      </p>
    </div>

    <p v-if="!data.average" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No polls are on file yet.</p>
    <div v-else class="mt-3 grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- The amalgamation: one bar per party, in the party's colour. -->
      <div>
        <ul class="space-y-2">
          <li v-for="[party, a] in parties" :key="party" class="flex items-center gap-3 text-sm">
            <span class="w-24 shrink-0 truncate font-medium text-gray-900 dark:text-white" :title="party">{{ party }}</span>
            <span class="relative h-5 min-w-0 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-gray-700" role="img" :aria-label="`${party}: ${a.percent} percent`">
              <span :class="partyStyle(party)?.bar ?? 'bg-gray-500'" class="absolute inset-y-0 left-0 rounded" :style="{ width: `${Math.min(100, (a.percent / scale) * 100)}%` }" />
            </span>
            <span class="w-12 shrink-0 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ a.percent.toFixed(1) }}%</span>
          </li>
        </ul>
        <p class="mt-3 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
          An unweighted average of each pollster's most recent poll that ended in the field within {{ data.average.window_days }} days of the newest, in percent of decided and leaning voters. A party a pollster didn't report is averaged over the polls that did. It summarizes published polls; it is not a forecast of seats.
        </p>
      </div>

      <!-- Every poll on file: a fixed-height list that loads more as it is scrolled. -->
      <div>
        <div ref="box" class="h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700" role="region" aria-label="Polls" tabindex="0">
          <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
            <li v-for="p in visible" :key="p.id" class="px-3 py-2.5 text-sm">
              <div class="flex items-baseline justify-between gap-3">
                <a v-if="isHttpUrl(p.source_url)" :href="p.source_url" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate font-medium text-gray-900 hover:underline dark:text-white">{{ p.pollster }}</a>
                <span v-else class="font-medium text-gray-900 dark:text-white">{{ p.pollster }}</span>
                <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ fielded(p) }}</span>
              </div>
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-for="[party, pct] in resultsOf(p)" :key="party" :class="partyStyle(party)?.solid ?? 'bg-gray-600 text-white'" class="rounded px-1.5 py-0.5 text-[11px] font-bold tabular-nums" :title="party">{{ partyStyle(party)?.short ?? party }} {{ pct }}</span>
              </div>
              <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                {{ [p.sample_size ? `n=${p.sample_size.toLocaleString()}` : null, p.method].filter(Boolean).join(' · ') }}<span v-if="p.in_average" class="font-semibold text-indigo-600 dark:text-indigo-400"> · in the average</span>
                <span v-if="p.note"> · {{ p.note }}</span>
              </p>
            </li>
          </ul>
          <div v-if="hasMore" ref="end" class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">Loading more…</div>
          <p v-else class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">All {{ data.polls.length }} polls shown.</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { partyStyle } from './party'
import { sectionHeadingClass } from '../Partials/classes'
import { formatDate, isHttpUrl } from '../Partials/format'
import { useInfiniteScroll } from '../Partials/infiniteScroll'
import type { PollData, PollRow } from '../Partials/types'

const props = defineProps<{ data: PollData }>()

const { box, end, visible, hasMore } = useInfiniteScroll(() => props.data.polls, 10)

const parties = computed(() => Object.entries(props.data.average?.parties ?? {}))
// Bars are drawn against the largest average, with a little headroom.
const scale = computed(() => Math.max(1, ...parties.value.map(([, a]) => a.percent)) * 1.1)

const resultsOf = (p: PollRow) => Object.entries(p.results).sort((a, b) => b[1] - a[1])
const fielded = (p: PollRow) => (p.fielded_from && p.fielded_from !== p.fielded_to ? `${formatDate(p.fielded_from)} to ${formatDate(p.fielded_to)}` : formatDate(p.fielded_to))
</script>
