<template>
  <AdminShowShell wide>
    <template #mobile-header>
      <AdminMobileHeader title="Poll history" :href="dashboardHref" />
    </template>

    <template #header>
      <div class="flex items-center gap-4">
        <Link :href="dashboardHref" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">
          &larr; Back to Elections
        </Link>
        <QuickSearch :election="election.slug" />
      </div>
      <h1 class="hidden md:block mt-2 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">Province-wide poll history</h1>
    </template>

    <div class="space-y-6 md:mt-6">
      <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
        Every province-wide poll on file for 2026, in percent of decided and leaning voters. Each line is a smoothed trend through the polls for that party, so a single outlier doesn't move it much.
      </p>

      <p v-if="!polls.length" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">No polls are on file yet.</p>

      <template v-else>
        <!-- Legend and toggles: each party with where its trend stands and how far it has moved in 30 days. -->
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <li v-for="s in series" :key="s.party">
            <button
              type="button"
              :aria-pressed="!hidden.has(s.party)"
              class="tap-target-touch flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left transition"
              :class="hidden.has(s.party) ? 'border-gray-200 bg-gray-50 opacity-60 dark:border-gray-700 dark:bg-gray-800/50' : 'border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800'"
              @click="toggle(s.party)"
            >
              <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: s.color }" aria-hidden="true" />
              <span class="min-w-0">
                <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ s.party }}</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                  <template v-if="s.latest !== null">{{ s.latest.toFixed(1) }}%</template>
                  <template v-if="s.change !== null"> · <span :class="changeClass(s.change)">{{ s.change > 0 ? '+' : '' }}{{ s.change.toFixed(1) }}</span> in 30 days</template>
                  <template v-else-if="s.points.length < 3"> · too few polls for a trend</template>
                </span>
              </span>
            </button>
          </li>
        </ul>

        <!-- The chart: dates across, percent up, with the day the election was called and voting day marked. -->
        <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800 sm:p-4">
          <div ref="chartBox" class="w-full">
          <svg :viewBox="`0 0 ${W} ${H}`" :width="W" :height="H" class="block h-auto w-full text-gray-500 dark:text-gray-400" role="img" aria-label="Line chart of province-wide poll results by party, January to October 2026">
            <!-- Horizontal grid and the percent scale. -->
            <g :font-size="compact ? 12 : 11" fill="currentColor">
              <g v-for="t in yTicks" :key="t">
                <line :x1="M.left" :x2="W - M.right" :y1="y(t)" :y2="y(t)" stroke="currentColor" stroke-opacity="0.15" />
                <text :x="M.left - 8" :y="y(t) + 4" text-anchor="end">{{ t }}%</text>
              </g>
              <!-- Month ticks along the bottom. -->
              <g v-for="m in xTicks" :key="m.label">
                <line :x1="m.x" :x2="m.x" :y1="H - M.bottom" :y2="H - M.bottom + 5" stroke="currentColor" stroke-opacity="0.5" />
                <text :x="m.x" :y="H - M.bottom + 19" text-anchor="middle">{{ m.label }}</text>
              </g>
            </g>

            <!-- Election called, and voting day. On phones the two lines are close together, so the labels run up the lines. -->
            <g :font-size="compact ? 10 : 11" fill="currentColor">
              <line :x1="x(calledOn)" :x2="x(calledOn)" :y1="M.top" :y2="H - M.bottom" stroke="currentColor" stroke-opacity="0.6" stroke-dasharray="4 3" />
              <line :x1="x(votingDay)" :x2="x(votingDay)" :y1="M.top" :y2="H - M.bottom" stroke="currentColor" stroke-opacity="0.6" stroke-dasharray="4 3" />
              <template v-if="compact">
                <text :transform="`translate(${x(calledOn) - 5} ${M.top + 4}) rotate(90)`" text-anchor="start">Election called</text>
                <text :transform="`translate(${x(votingDay) - 5} ${M.top + 4}) rotate(90)`" text-anchor="start">Voting day</text>
              </template>
              <template v-else>
                <text :x="x(calledOn) - 4" :y="M.top + 11" text-anchor="end">Election called</text>
                <text :x="x(votingDay) - 4" :y="M.top + 24" text-anchor="end">Voting day</text>
              </template>
            </g>

            <!-- One smoothed trend line per party. -->
            <g v-for="s in visibleSeries" :key="s.party" fill="none" :stroke="s.color">
              <path v-if="s.path" :d="s.path" stroke-width="2.75" stroke-linejoin="round" stroke-linecap="round">
                <title>{{ s.party }}: {{ s.latest?.toFixed(1) }}% now</title>
              </path>
            </g>
          </svg>
          </div>
          <p class="mt-2 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
            Trend: a kernel-weighted average of each party's polls (weights fall off over about two weeks and rise with sample size), drawn only between that party's first and last poll, so it is least certain at the ends. Parties with fewer than three polls get no line. The change shown is the trend line now against 30 days earlier. Polls from Mainstreet, Pallas, Léger and others before August come from 338Canada's table and report OneBC and CentreBC only where the table made clear which was which.
          </p>
        </div>

        <!-- The same numbers as a table. -->
        <details class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
          <summary class="tap-target-touch cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">The polls behind the chart ({{ polls.length }})</summary>
          <div class="mt-3 max-h-96 overflow-auto">
            <table class="w-full text-left text-sm">
              <thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                  <th class="py-1.5 pr-3 font-medium">Pollster</th>
                  <th class="py-1.5 pr-3 font-medium">Ended</th>
                  <th v-for="s in series" :key="s.party" class="py-1.5 pr-3 text-right font-medium">{{ partyStyle(s.party)?.short ?? s.party }}</th>
                  <th class="py-1.5 text-right font-medium">n</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <tr v-for="p in polls" :key="p.id">
                  <td class="py-1.5 pr-3"><a v-if="isHttpUrl(p.source_url)" :href="p.source_url" target="_blank" rel="noopener noreferrer" class="text-gray-900 hover:underline dark:text-white">{{ p.pollster }}</a><span v-else>{{ p.pollster }}</span></td>
                  <td class="whitespace-nowrap py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ formatDate(p.fielded_to) }}</td>
                  <td v-for="s in series" :key="s.party" class="py-1.5 pr-3 text-right tabular-nums text-gray-800 dark:text-gray-200">{{ p.results[s.party] ?? '' }}</td>
                  <td class="py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ p.sample_size?.toLocaleString() ?? '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </details>
      </template>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { trail, provincial } from '../Partials/breadcrumbs'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import { partyStyle } from './party'
import QuickSearch from '../Partials/QuickSearch.vue'
import { formatDate, isHttpUrl } from '../Partials/format'
import type { ElectionInfo, PollData } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true, breadcrumbs: trail(provincial(), { label: 'Poll history' }) }, () => page) })

const props = defineProps<PollData & { election: ElectionInfo; calledOn: string; votingDay: string }>()

const dashboardHref = route('admin.elections.provincial')

// ---- Chart geometry: drawn at the container's real width, so text and lines keep their size on a phone ----
const chartBox = ref<HTMLElement | null>(null)
const width = ref(800)
let resizeObserver: ResizeObserver | null = null
onMounted(() => {
  if (!chartBox.value) return
  width.value = Math.round(chartBox.value.clientWidth) || 800
  resizeObserver = new ResizeObserver(([entry]) => {
    width.value = Math.round(entry.contentRect.width) || width.value
  })
  resizeObserver.observe(chartBox.value)
})
onBeforeUnmount(() => resizeObserver?.disconnect())

const compact = computed(() => width.value < 560)
const W = computed(() => Math.max(260, width.value))
const H = computed(() => (compact.value ? 340 : 400))
const M = computed(() => (compact.value ? { top: 14, right: 10, bottom: 32, left: 38 } : { top: 14, right: 16, bottom: 30, left: 44 }))
const DAY = 86_400_000
const t = (iso: string) => new Date(`${iso}T12:00:00`).getTime()

const earliest = computed(() => Math.min(...props.polls.map((p) => t(p.fielded_to))))
const latest = computed(() => Math.max(...props.polls.map((p) => t(p.fielded_to))))
// Dates run from a little before the first poll to voting day.
const xMin = computed(() => earliest.value - 5 * DAY)
const xMax = computed(() => Math.max(t(props.votingDay), latest.value) + 2 * DAY)
const x = (iso: string | number) => M.value.left + (((typeof iso === 'number' ? iso : t(iso)) - xMin.value) / (xMax.value - xMin.value)) * (W.value - M.value.left - M.value.right)

const yMax = computed(() => Math.ceil(Math.max(...props.polls.flatMap((p) => Object.values(p.results))) / 10) * 10 + 5)
const y = (v: number) => H.value - M.value.bottom - (v / yMax.value) * (H.value - M.value.top - M.value.bottom)
const yTicks = computed(() => Array.from({ length: Math.floor(yMax.value / 10) + 1 }, (_, i) => i * 10))
const xTicks = computed(() => {
  const out: { x: number; label: string }[] = []
  const d = new Date(xMin.value)
  d.setDate(1)
  d.setMonth(d.getMonth() + 1)
  let i = 0
  while (d.getTime() < xMax.value) {
    if (!compact.value || i % 2 === 0) out.push({ x: x(d.getTime()), label: d.toLocaleDateString(undefined, { month: 'short' }) })
    d.setMonth(d.getMonth() + 1)
    i++
  }
  return out
})

// ---- Series: each party's points and its smoothed trend ----
const COLORS: Record<string, string> = { NDP: '#ea580c', CON: '#1d4ed8', GRN: '#16a34a', CTR: '#0d9488', ONE: '#be123c' }
const colorOf = (party: string) => COLORS[partyStyle(party)?.short ?? ''] ?? '#6b7280'

const BANDWIDTH_DAYS = 14
const MIN_POLLS_FOR_TREND = 3

/** The trend at one moment: polls weighted by closeness in time (Gaussian) and by the square root of their sample. */
const trendAt = (points: { time: number; value: number; n: number }[], at: number): number => {
  let num = 0
  let den = 0
  for (const p of points) {
    const w = Math.exp(-0.5 * (((p.time - at) / DAY / BANDWIDTH_DAYS) ** 2)) * Math.sqrt(p.n || 800)
    num += w * p.value
    den += w
  }
  return den > 0 ? num / den : NaN
}

const series = computed(() => {
  const parties = [...new Set(props.polls.flatMap((p) => Object.keys(p.results)))]
  return parties
    .map((party) => {
      const points = props.polls
        .filter((p) => p.results[party] !== undefined)
        .map((p) => ({ id: p.id, pollster: p.pollster, date: p.fielded_to, time: t(p.fielded_to), value: p.results[party], n: p.sample_size ?? 800 }))
        .sort((a, b) => a.time - b.time)
      let path = ''
      let latestTrend: number | null = null
      let change: number | null = null
      if (points.length >= MIN_POLLS_FOR_TREND) {
        const first = points[0].time
        const last = points[points.length - 1].time
        const coords: string[] = []
        for (let at = first; at <= last; at += DAY) coords.push(`${coords.length ? 'L' : 'M'}${x(at).toFixed(1)},${y(trendAt(points, at)).toFixed(1)}`)
        coords.push(`L${x(last).toFixed(1)},${y(trendAt(points, last)).toFixed(1)}`)
        path = coords.join(' ')
        latestTrend = trendAt(points, last)
        if (last - 30 * DAY >= first) change = latestTrend - trendAt(points, last - 30 * DAY)
      } else if (points.length) {
        latestTrend = points[points.length - 1].value
      }
      return { party, color: colorOf(party), points, path, latest: latestTrend, change }
    })
    // Biggest first, by where each party stands now.
    .sort((a, b) => (b.latest ?? 0) - (a.latest ?? 0))
})

const hidden = reactive(new Set<string>())
const toggle = (party: string) => (hidden.has(party) ? hidden.delete(party) : hidden.add(party))
const visibleSeries = computed(() => series.value.filter((s) => !hidden.has(s.party)))

const changeClass = (v: number) => (Math.abs(v) < 0.5 ? 'text-gray-500 dark:text-gray-400' : v > 0 ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'font-semibold text-rose-700 dark:text-rose-400')
</script>
