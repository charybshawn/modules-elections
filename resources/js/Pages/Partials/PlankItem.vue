<template>
  <article :id="`plank-${plank.key}`" class="scroll-mt-32">
    <div class="flex items-start gap-3">
      <span class="mt-0.5 inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200" :aria-label="`Ranked ${position}`">{{ position }}</span>
      <div class="min-w-0 flex-1">
        <h3 class="text-base font-medium text-gray-900 dark:text-white">
          {{ plank.title }}
          <span v-if="isNew" :class="newPillClass" class="ml-1 align-middle">New</span>
        </h3>
        <p v-if="plank.summary" class="mt-1 text-sm sm:text-base leading-relaxed text-gray-700 dark:text-gray-300">{{ plank.summary }}</p>

        <!-- The signals behind the rank, so the order is never a black box. -->
        <ul class="mt-2 flex flex-wrap gap-1.5 text-xs">
          <li v-if="!plank.tags.length" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ topics[plank.topic] ?? plank.topic }}</li>
          <li v-if="plank.priority_position" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Their priority #{{ plank.priority_position }}</li>
          <li v-if="plank.plan" :class="planStyle(plank.plan.status).chip" class="rounded px-1.5 py-0.5">{{ options.planStatuses[plank.plan.status] ?? plank.plan.status }}</li>
          <li v-if="plank.has_commitment" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Specific commitment</li>
          <li class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ plank.source_count }} source{{ plank.source_count === 1 ? '' : 's' }}</li>
          <li v-if="movement" :class="movement.up ? 'bg-green-50 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded px-1.5 py-0.5">{{ movement.label }}</li>
        </ul>
        <TagChips :tags="plank.tags" :as-filter="activeTag !== undefined" :active="activeTag ?? null" class="mt-2" @select="$emit('tag', $event)" />
        <p v-if="plank.rationale" class="mt-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Why it ranks here:</span> {{ plank.rationale }}</p>

        <details v-if="plank.history.length > 1" class="mt-2">
          <summary class="tap-target-touch inline-flex cursor-pointer items-center text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Rank history</summary>
          <ol class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
            <li v-for="(point, i) in plank.history" :key="i">{{ formatDate(point.recorded_at) }} · #{{ point.rank }} · {{ tiers[point.tier] ?? point.tier }}</li>
          </ol>
        </details>

        <!-- Two columns on desktop: their words on the left, their plan on the
             right. On phones the plan comes first and the words fold away. -->
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
          <section v-if="plank.sources.length" class="min-w-0">
            <h4 class="hidden text-xs font-semibold uppercase tracking-wide text-gray-500 md:block dark:text-gray-400">What they said ({{ plank.sources.length }})</h4>
            <button
              type="button"
              :aria-expanded="wordsOpen"
              class="tap-target-touch inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 md:hidden dark:text-indigo-400 dark:hover:text-indigo-300"
              @click="wordsOpen = !wordsOpen"
            >
              What they said ({{ plank.sources.length }})
              <svg :class="wordsOpen ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <ul :class="wordsOpen ? 'block' : 'hidden'" class="mt-3 space-y-4 border-l-2 border-gray-200 pl-4 md:mt-2 md:block dark:border-gray-700">
              <li v-for="entry in plank.sources" :key="entry.id">
                <EntryCard :entry="entry" :options="options" :editable="editable" :is-new="isNewEntry(entry)" @delete="$emit('delete-entry', entry)" />
              </li>
            </ul>
          </section>

          <section :class="[planStyle(plank.plan?.status).panel, plank.sources.length ? '' : 'md:col-span-2']" class="order-first min-w-0 self-start rounded-lg border p-4 md:order-none">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Their plan</h4>
              <span v-if="plank.plan" :class="planStyle(plank.plan.status).chip" class="rounded-full px-2 py-0.5 text-xs font-medium">{{ options.planStatuses[plank.plan.status] ?? plank.plan.status }}</span>
            </div>
            <template v-if="plank.plan">
              <p v-if="plank.plan.summary" class="mt-2 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ plank.plan.summary }}</p>
              <dl v-if="plank.plan.details.length" class="mt-3 space-y-3">
                <div v-for="group in planGroups" :key="group.aspect">
                  <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ options.planAspects[group.aspect] ?? group.aspect }}</dt>
                  <dd v-for="(d, i) in group.details" :key="i" class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                    {{ d.text }}
                    <a v-if="isHttpUrl(d.source_url)" :href="d.source_url" target="_blank" rel="noopener noreferrer" class="ml-1 whitespace-nowrap text-xs text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">{{ hostOf(d.source_url) }}</a>
                  </dd>
                </div>
              </dl>
            </template>
            <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">Not assessed yet -- the next research pass will say what they've conveyed about carrying this out.</p>
          </section>
        </div>

        <!-- AI analysis (pillars only): collapsed to its plain-language meaning until opened. -->
        <AnalysisPanel v-if="plank.analysis" :analysis="plank.analysis" :labels="options.analysisParts" :id-prefix="plank.key" class="mt-4" />

        <button v-if="editable" type="button" class="tap-target-touch mt-2 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', plank)">Delete plank</button>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import EntryCard from './EntryCard.vue'
import AnalysisPanel from './AnalysisPanel.vue'
import TagChips from './TagChips.vue'
import { newPillClass } from './classes'
import { formatDate, hostOf, isHttpUrl } from './format'
import type { Entry, Options, Plank } from './types'

const props = defineProps<{
  plank: Plank
  /** 1-based position across the whole platform. */
  position: number
  topics: Record<string, string>
  options: Options
  editable?: boolean
  isNew?: boolean
  isNewEntry: (entry: Entry) => boolean
  /** When set (even to null), tag chips filter the platform in place instead of linking out. */
  activeTag?: string | null
}>()

defineEmits<{ delete: [plank: Plank]; 'delete-entry': [entry: Entry]; tag: [slug: string | null] }>()

const tiers = computed(() => props.options.plankTiers)

/** Phones only: "What they said" starts folded under the plan. */
const wordsOpen = ref(false)

/** Plan details grouped by aspect, in the server's aspect order. */
const planGroups = computed(() => {
  const details = props.plank.plan?.details ?? []
  return Object.keys(props.options.planAspects)
    .map((aspect) => ({ aspect, details: details.filter((d) => d.aspect === aspect) }))
    .filter((g) => g.details.length)
})

const planStyle = (status: string | undefined) =>
  ({
    specific: { panel: 'border-green-200 bg-green-50/60 dark:border-green-500/30 dark:bg-green-500/5', chip: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' },
    partial: { panel: 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/5', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' },
    none: { panel: 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/60', chip: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' },
  })[status ?? ''] ?? { panel: 'border-dashed border-gray-300 dark:border-gray-600', chip: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }

// Compared with where it stood before its latest change: rank first, then
// tier (a plank can change tier while keeping its number).
const movement = computed(() => {
  const history = props.plank.history
  if (history.length < 2) return null
  const before = history[history.length - 2]
  const now = history[history.length - 1]
  if (now.rank !== before.rank) {
    return { up: now.rank < before.rank, label: `${now.rank < before.rank ? 'Up' : 'Down'} from #${before.rank}` }
  }
  if (now.tier !== before.tier) {
    return { up: false, label: `Was ${tiers.value[before.tier] ?? before.tier}` }
  }
  return null
})
</script>
