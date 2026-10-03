<template>
  <section class="rounded-lg border border-violet-200 bg-violet-50/50 dark:border-violet-500/30 dark:bg-violet-500/5">
    <!-- Header doubles as the toggle: collapsed shows only the plain-language meaning. -->
    <button
      type="button"
      :aria-expanded="open"
      :aria-controls="bodyId"
      class="tap-target-touch flex w-full items-center justify-between gap-2 px-4 pt-3 text-left"
      :class="open ? '' : 'pb-1'"
      @click="open = !open"
    >
      <span class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">AI analysis</span>
      <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
        <span v-if="analysis.on" class="hidden sm:inline">{{ formatDate(analysis.on) }} ·</span>
        {{ open ? 'Less' : 'More' }}
        <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
      </span>
    </button>

    <div class="px-4 pb-4">
      <!-- 1. What this means: always visible. -->
      <p v-for="(point, i) in part('meaning')" :key="`m${i}`" class="mt-1.5 text-sm leading-relaxed text-gray-900 sm:text-base dark:text-gray-100">
        {{ point.text }}<FootnoteRefs :sources="point.sources" :number="footnoteNumber" :anchor="footnoteId" />
      </p>

      <div v-show="open" :id="bodyId">
        <!-- 2 and 3. The case for and the case against, side by side on desktop. -->
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div v-for="side in (['works', 'fails'] as const)" :key="side" class="min-w-0">
            <h5 :class="side === 'works' ? 'text-green-800 dark:text-green-300' : 'text-amber-800 dark:text-amber-300'" class="text-xs font-semibold">{{ labels[side] }}</h5>
            <ul v-if="part(side).length" class="mt-1 list-disc space-y-1.5 pl-4 text-sm text-gray-800 marker:text-violet-400 dark:text-gray-200">
              <li v-for="(point, i) in part(side)" :key="i">
                {{ point.text }}<FootnoteRefs :sources="point.sources" :number="footnoteNumber" :anchor="footnoteId" />
              </li>
            </ul>
            <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nothing noted.</p>
          </div>
        </div>

        <!-- 4. The devil is in the details: the short conclusion. -->
        <div v-if="part('details').length" class="mt-4 rounded-md border-l-4 border-violet-400 bg-white/70 px-3 py-2 dark:bg-gray-900/40">
          <h5 class="text-xs font-semibold text-violet-800 dark:text-violet-300">{{ labels.details }}</h5>
          <p v-for="(point, i) in part('details')" :key="`d${i}`" class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
            {{ point.text }}<FootnoteRefs :sources="point.sources" :number="footnoteNumber" :anchor="footnoteId" />
          </p>
        </div>

        <!-- Numbered sources, in order of first citation. -->
        <ol v-if="footnotes.length" class="mt-4 space-y-0.5 border-t border-violet-200 pt-2 text-xs text-gray-500 dark:border-violet-500/30 dark:text-gray-400">
          <li v-for="(url, i) in footnotes" :id="footnoteId(url)" :key="url" class="flex gap-1.5">
            <span class="w-4 shrink-0 text-right tabular-nums">{{ i + 1 }}.</span>
            <a :href="url" target="_blank" rel="noopener noreferrer" class="min-w-0 break-all underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">{{ footnoteLabel(url) }}</a>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import FootnoteRefs from './FootnoteRefs.vue'
import { formatDate } from './format'
import type { PlankAnalysis } from './types'

const props = withDefaults(
  defineProps<{
    analysis: PlankAnalysis
    /** Options.analysisParts: part key -> heading. */
    labels: Record<string, string>
    /** Unique per panel on the page, for footnote anchors (e.g. candidate slug + plank key). */
    idPrefix: string
    /** Start expanded rather than showing only the meaning. */
    startOpen?: boolean
  }>(),
  { startOpen: false },
)

const open = ref(props.startOpen)
const bodyId = computed(() => `analysis-${props.idPrefix}`)
const part = (key: string) => props.analysis.parts[key] ?? []

// One number per source, in order of first citation across the panel.
const footnotes = computed(() => {
  const seen: string[] = []
  for (const key of Object.keys(props.labels)) {
    for (const point of part(key)) for (const url of point.sources) if (!seen.includes(url)) seen.push(url)
  }
  return seen
})
const footnoteNumber = (url: string) => footnotes.value.indexOf(url) + 1
const footnoteId = (url: string) => `fn-${props.idPrefix}-${footnoteNumber(url)}`
/** Host plus a readable path, e.g. "castanet.net › New data shows increased number…". */
const footnoteLabel = (url: string) => {
  try {
    const u = new URL(url)
    const slug = decodeURIComponent(u.pathname.split('/').filter(Boolean).pop() ?? '').replace(/[-_]+/g, ' ').replace(/\.(html?|pdf)$/i, '')
    return slug ? `${u.hostname.replace(/^www\./, '')} › ${slug.length > 70 ? `${slug.slice(0, 70)}…` : slug}` : u.hostname
  } catch {
    return url
  }
}
</script>
