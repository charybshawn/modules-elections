<template>
  <li :id="`plank-${plank.key}`" class="scroll-mt-32">
    <!-- The row: everything needed to scan the platform. Tap to open. -->
    <button
      type="button"
      :aria-expanded="open"
      :aria-controls="`plank-body-${plank.key}`"
      :class="open ? 'bg-gray-50 dark:bg-gray-700/40' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'"
      class="tap-target-touch flex w-full items-start gap-3 rounded-lg px-2 py-2.5 text-left transition-colors"
      @click="$emit('toggle')"
    >
      <span class="mt-0.5 inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200" :aria-label="`Ranked ${position}`">{{ position }}</span>
      <span class="min-w-0 flex-1">
        <span class="block text-sm font-medium text-gray-900 sm:text-base dark:text-white">
          {{ plank.title }}
          <span v-if="isNew" :class="newPillClass" class="ml-1 align-middle">New</span>
        </span>
        <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
          <span :class="planStyle(plank.plan?.status).chip" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5" :title="plan ? planLabel : 'Plan not assessed yet'">
            <span aria-hidden="true">{{ planStyle(plank.plan?.status).icon }}</span>{{ planLabel }}
          </span>
          <span v-if="plank.analysis" class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200">AI analysis</span>
          <span v-if="movement" :class="movement.up ? 'bg-green-50 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded px-1.5 py-0.5">{{ movement.label }}</span>
          <span class="text-gray-500 dark:text-gray-400">{{ plank.source_count }} source{{ plank.source_count === 1 ? '' : 's' }}</span>
          <span v-for="tag in plank.tags" :key="tag.slug" class="hidden rounded-full bg-indigo-50 px-1.5 py-0.5 text-indigo-700 sm:inline dark:bg-indigo-500/10 dark:text-indigo-300">{{ tag.name }}</span>
        </span>
      </span>
      <svg :class="open ? 'rotate-180' : ''" class="mt-1 h-5 w-5 shrink-0 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
    </button>

    <!-- Opened: summary and why it ranks, then the plan beside their words, then the analysis. -->
    <div v-if="open" :id="`plank-body-${plank.key}`" class="pb-4 pl-2 pr-1 sm:pl-11 sm:pr-2">
      <p v-if="plank.summary" class="text-sm leading-relaxed text-gray-700 sm:text-base dark:text-gray-300">{{ plank.summary }}</p>
      <p v-if="plank.rationale" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium text-gray-600 dark:text-gray-300">Why it ranks here:</span> {{ plank.rationale }}
        <template v-if="plank.priority_position"> · Their priority #{{ plank.priority_position }}</template>
        <template v-if="plank.history.length > 1">
          · <button type="button" class="underline underline-offset-2 hover:text-gray-800 dark:hover:text-gray-200" @click="showHistory = !showHistory">rank history</button>
        </template>
      </p>
      <ol v-if="showHistory" class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
        <li v-for="(point, i) in plank.history" :key="i">{{ formatDate(point.recorded_at) }} · #{{ point.rank }} · {{ options.plankTiers[point.tier] ?? point.tier }}</li>
      </ol>
      <TagChips :tags="plank.tags" :as-filter="activeTag !== undefined" :active="activeTag ?? null" class="mt-2 sm:hidden" @select="$emit('tag', $event)" />

      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5 lg:gap-6">
        <!-- Their plan: what voters most want to know, so it leads. -->
        <section :class="planStyle(plank.plan?.status).panel" class="min-w-0 self-start rounded-lg border p-4 lg:col-span-3">
          <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Their plan</h4>
          <template v-if="plan">
            <p v-if="plan.summary" class="mt-1.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ plan.summary }}</p>
            <dl v-if="plan.details.length" class="mt-3 space-y-2.5">
              <div v-for="group in planGroups" :key="group.aspect">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ options.planAspects[group.aspect] ?? group.aspect }}</dt>
                <dd v-for="(d, i) in group.details" :key="i" class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                  {{ d.text }}
                  <a v-if="isHttpUrl(d.source_url)" :href="d.source_url" target="_blank" rel="noopener noreferrer" class="ml-1 whitespace-nowrap text-xs text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">{{ hostOf(d.source_url) }}</a>
                </dd>
              </div>
            </dl>
          </template>
          <p v-else class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Not assessed yet.</p>
        </section>

        <!-- What they said: compact quotes, each opening to the full statement. -->
        <section v-if="plank.sources.length" class="min-w-0 lg:col-span-2">
          <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">What they said ({{ plank.sources.length }})</h4>
          <ul class="mt-2 space-y-3">
            <li v-for="entry in shownSources" :key="entry.id">
              <StatementLine :entry="entry" :options="options" :editable="editing" :is-new="isNewEntry(entry)" @delete="$emit('delete-entry', entry)" />
            </li>
          </ul>
          <button v-if="plank.sources.length > SOURCES_SHOWN" type="button" class="tap-target-touch mt-2 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="allSources = !allSources">
            {{ allSources ? 'Show fewer' : `Show all ${plank.sources.length}` }}
          </button>
        </section>
      </div>

      <AnalysisPanel v-if="plank.analysis" :analysis="plank.analysis" :labels="options.analysisParts" :id-prefix="plank.key" class="mt-4" />

      <button v-if="editing" type="button" class="tap-target-touch mt-3 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', plank)">Delete plank</button>
    </div>
  </li>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import AnalysisPanel from './AnalysisPanel.vue'
import StatementLine from './StatementLine.vue'
import TagChips from './TagChips.vue'
import { newPillClass } from './classes'
import { formatDate, hostOf, isHttpUrl } from './format'
import type { Entry, Options, Plank } from './types'

const SOURCES_SHOWN = 3

const props = defineProps<{
  plank: Plank
  /** 1-based position across the whole platform. */
  position: number
  open: boolean
  options: Options
  /** Admin edit mode: delete controls show. */
  editing?: boolean
  isNew?: boolean
  isNewEntry: (entry: Entry) => boolean
  /** When set (even to null), tag chips filter the platform in place. */
  activeTag?: string | null
}>()
defineEmits<{ toggle: []; delete: [plank: Plank]; 'delete-entry': [entry: Entry]; tag: [slug: string | null] }>()

const plan = computed(() => props.plank.plan)
const planLabel = computed(() => (plan.value ? (props.options.planStatuses[plan.value.status] ?? plan.value.status) : 'Plan not assessed'))
const showHistory = ref(false)
const allSources = ref(false)
const shownSources = computed(() => (allSources.value ? props.plank.sources : props.plank.sources.slice(0, SOURCES_SHOWN)))

/** Plan details grouped by aspect, in the server's aspect order. */
const planGroups = computed(() => {
  const details = plan.value?.details ?? []
  return Object.keys(props.options.planAspects)
    .map((aspect) => ({ aspect, details: details.filter((d) => d.aspect === aspect) }))
    .filter((g) => g.details.length)
})

const planStyle = (status: string | undefined) =>
  ({
    specific: { icon: '✓', panel: 'border-green-200 bg-green-50/60 dark:border-green-500/30 dark:bg-green-500/5', chip: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' },
    partial: { icon: '~', panel: 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/5', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' },
    none: { icon: '–', panel: 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60', chip: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' },
  })[status ?? ''] ?? { icon: '?', panel: 'border-dashed border-gray-300 dark:border-gray-600', chip: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }

// Compared with where it stood before its latest change: rank first, then tier.
const movement = computed(() => {
  const history = props.plank.history
  if (history.length < 2) return null
  const before = history[history.length - 2]
  const now = history[history.length - 1]
  if (now.rank !== before.rank) return { up: now.rank < before.rank, label: `${now.rank < before.rank ? 'Up' : 'Down'} from #${before.rank}` }
  if (now.tier !== before.tier) return { up: false, label: `Was ${props.options.plankTiers[before.tier] ?? before.tier}` }
  return null
})
</script>
