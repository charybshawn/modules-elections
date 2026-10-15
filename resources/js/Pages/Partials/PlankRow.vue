<template>
  <li :id="`plank-${plank.key}`" class="scroll-mt-32">
    <!-- The row: everything needed to scan the platform. Tap to open it in a modal. -->
    <button
      type="button"
      aria-haspopup="dialog"
      :class="[open ? 'bg-gray-50 dark:bg-gray-700/40' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30', focus === `plank-${plank.key}` ? focusClass : '']"
      class="tap-target-touch flex w-full items-start gap-3 rounded-lg px-2 py-2.5 text-left transition-colors"
      @click="$emit('toggle')"
    >
      <span class="mt-0.5 inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200" :aria-label="`Ranked ${position}`">{{ position }}</span>
      <span class="min-w-0 flex-1">
        <span class="block text-sm font-medium text-gray-900 sm:text-base dark:text-white">
          {{ plank.title }}
          <span v-if="isNew" :class="newPillClass" class="ml-1 align-middle">New</span>
          <span v-else-if="isUpdated" :class="newPillClass" class="ml-1 align-middle">Updated</span>
        </span>
        <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
          <span v-if="plank.analysis" class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200">AI analysis</span>
          <span v-if="movement" :class="movement.up ? 'bg-green-50 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded px-1.5 py-0.5">{{ movement.label }}</span>
          <span class="text-gray-500 dark:text-gray-400">{{ plank.source_count }} source{{ plank.source_count === 1 ? '' : 's' }}</span>
          <span v-for="tag in plank.tags" :key="tag.slug" class="hidden rounded-full bg-indigo-50 px-1.5 py-0.5 text-indigo-700 sm:inline dark:bg-indigo-500/10 dark:text-indigo-300">{{ tag.name }}</span>
        </span>
      </span>
      <svg class="mt-1 h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
    </button>

    <!-- Opened: a modal on desktop, a full-page one on phones. Summary and why it
         ranks, then their words, then the analysis. -->
    <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[60] flex items-stretch justify-center bg-gray-900/50 sm:items-center sm:p-6" @mousedown.self="$emit('close')">
    <div
      :id="`plank-body-${plank.key}`"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`plank-title-${plank.key}`"
      class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-4xl sm:rounded-xl dark:bg-gray-800"
      @keydown.esc.stop="$emit('close')"
    >
      <div class="flex items-start gap-3 border-b border-gray-200 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-6 sm:pt-4 dark:border-gray-700">
        <span class="mt-0.5 inline-flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">{{ position }}</span>
        <h3 :id="`plank-title-${plank.key}`" class="min-w-0 flex-1 text-base font-semibold text-gray-900 sm:text-lg dark:text-white">{{ plank.title }}</h3>
        <button ref="closeButton" type="button" class="tap-target-touch shrink-0 rounded-md p-1 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" aria-label="Close" @click="$emit('close')">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
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

      <div class="mt-4">
        <!-- What they said: compact quotes, each opening to the full statement. -->
        <section v-if="plank.sources.length" class="min-w-0">
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
    </div>
    </div>
    </Teleport>
  </li>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import AnalysisPanel from './AnalysisPanel.vue'
import StatementLine from './StatementLine.vue'
import TagChips from './TagChips.vue'
import { newPillClass } from './classes'
import { focusClass, useFocus } from './focus'
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
  /** Edited since the last look (a new plan, analysis or rank) without being new. */
  isUpdated?: boolean
  isNewEntry: (entry: Entry) => boolean
  /** When set (even to null), tag chips filter the platform in place. */
  activeTag?: string | null
}>()
defineEmits<{ toggle: []; close: []; delete: [plank: Plank]; 'delete-entry': [entry: Entry]; tag: [slug: string | null] }>()

const showHistory = ref(false)

// While the modal is open the page behind it stays put, and focus moves in.
const closeButton = ref<HTMLButtonElement | null>(null)
let restoreOverflow: string | null = null
const lockScroll = (lock: boolean) => {
  if (lock && restoreOverflow === null) {
    restoreOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else if (!lock && restoreOverflow !== null) {
    document.body.style.overflow = restoreOverflow
    restoreOverflow = null
  }
}
watch(
  () => props.open,
  (open) => {
    lockScroll(open)
    if (open) nextTick(() => closeButton.value?.focus())
  },
  { immediate: true },
)
onBeforeUnmount(() => lockScroll(false))
const focus = useFocus()
const allSources = ref(props.plank.sources.slice(SOURCES_SHOWN).some((e) => focus.value === `entry-${e.id}`))
const shownSources = computed(() => (allSources.value ? props.plank.sources : props.plank.sources.slice(0, SOURCES_SHOWN)))

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
