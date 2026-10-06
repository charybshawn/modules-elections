<template>
  <section v-if="heat.headings.length" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
    <div class="flex items-center justify-between gap-2">
      <h2 :class="sectionHeadingClass">What's being talked about</h2>
      <div class="inline-flex shrink-0 rounded-md bg-gray-100 p-0.5 dark:bg-gray-700" role="group" aria-label="Heat by">
        <button
          v-for="m in (['recent', 'all'] as const)"
          :key="m"
          type="button"
          :aria-pressed="view.mode === m"
          :class="view.mode === m ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'"
          class="rounded px-2 py-0.5 text-xs font-medium transition"
          @click="view.mode = m"
        >{{ m === 'recent' ? 'Recent' : 'All-time' }}</button>
      </div>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      {{ view.mode === 'recent' ? 'Mentions by candidates and the news, recent ones counting most.' : 'Total mentions over the campaign.' }}
      Tap one to see who campaigns on it.
    </p>

    <!-- Ranked list: a heat bar, the mention count, and ▲ when it's heating up this week. -->
    <!-- Two columns when stacked full-width (tablets); one in the desktop sidebar and on phones. -->
    <ol class="mt-3 space-y-0.5 sm:grid sm:grid-cols-2 sm:gap-x-6 sm:space-y-0 lg:block lg:space-y-0.5">
      <li v-for="(tag, i) in ranked" :key="tag.slug" :class="i >= PHONE_TOP && !showAll && selected !== tag.slug ? 'hidden sm:block' : ''">
        <button
          type="button"
          :aria-pressed="selected === tag.slug"
          :title="`${tag.mentions} mention${tag.mentions === 1 ? '' : 's'}, ${tag.recent} in the last two weeks`"
          :class="selected === tag.slug ? 'bg-indigo-50 ring-1 ring-indigo-300 dark:bg-indigo-500/15 dark:ring-indigo-500/40' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
          class="tap-target-touch grid w-full grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-2 rounded-md px-1.5 py-1 text-left"
          @click="$emit('select', selected === tag.slug ? null : tag.slug)"
        >
          <span class="text-right text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ i + 1 }}</span>
          <span class="min-w-0">
            <span class="block truncate text-sm text-gray-900 dark:text-gray-100">{{ tag.name }}</span>
            <span class="mt-0.5 block h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
              <span :class="barClass(share(tag))" class="block h-full rounded-full" :style="{ width: `${Math.max(4, share(tag) * 100)}%` }" />
            </span>
          </span>
          <span class="flex items-center gap-1 text-xs tabular-nums text-gray-500 dark:text-gray-400">
            {{ view.mode === 'recent' ? tag.recent : tag.mentions }}
            <span v-if="risingSlugs.has(tag.slug)" class="text-red-600 dark:text-red-400" aria-label="heating up this week">▲</span>
            <span v-else class="inline-block w-[0.7em]" aria-hidden="true" />
          </span>
        </button>
      </li>
    </ol>
    <button
      v-if="ranked.length > PHONE_TOP"
      type="button"
      class="tap-target-touch mt-1 inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 sm:hidden dark:text-gray-300"
      :aria-expanded="showAll"
      @click="showAll = !showAll"
    >
      {{ showAll ? 'Show top 5' : `Show top ${ranked.length}` }}
      <svg :class="showAll ? 'rotate-180' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
    </button>

    <p v-if="selected" class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-100 pt-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
      <span><span class="font-semibold text-gray-900 dark:text-white">{{ matchingCount }}</span> candidate{{ matchingCount === 1 ? '' : 's' }} campaign{{ matchingCount === 1 ? 's' : '' }} on <span class="font-semibold text-gray-900 dark:text-white">{{ selectedName }}</span>, listed first</span>
      <span class="inline-flex items-center gap-3">
        <Link :href="route('admin.elections.tags.show', selected)" class="tap-target-touch inline-flex items-center font-medium text-indigo-600 underline underline-offset-2 dark:text-indigo-400">Open subject</Link>
        <button type="button" class="tap-target-touch inline-flex items-center font-medium underline underline-offset-2" @click="$emit('select', null)">Clear</button>
      </span>
    </p>

    <Link :href="route('admin.elections.tags.index')" class="mt-3 inline-flex text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">All {{ allTags.length }} subjects &rarr;</Link>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Link, useRemember } from '@inertiajs/vue3'
import { sectionHeadingClass } from './classes'
import type { TagHeat } from './types'

const TOP = 10
/** Phones show the top five until asked for more. */
const PHONE_TOP = 5
const showAll = ref(false)

const props = defineProps<{
  heat: TagHeat
  /** The subject currently highlighted on the dashboard. */
  selected: string | null
  /** Candidates campaigning on the selected subject. */
  matchingCount: number
}>()
defineEmits<{ select: [slug: string | null] }>()

// useRemember returns a reactive object only when given one (a plain object comes back as a ref).
const view = useRemember(reactive<{ mode: 'recent' | 'all' }>({ mode: 'recent' }), 'elections-heat-mode') as { mode: 'recent' | 'all' }

type HeatTag = TagHeat['headings'][number]['tags'][number]
const allTags = computed<HeatTag[]>(() => props.heat.headings.flatMap((h) => h.tags))
const risingSlugs = computed(() => new Set(props.heat.heatingUp.map((t) => t.slug)))
const selectedName = computed(() => allTags.value.find((t) => t.slug === props.selected)?.name ?? '')

const score = (t: HeatTag) => (view.mode === 'recent' ? t.heat : t.mentions)
const maxScore = computed(() => Math.max(0, ...allTags.value.map(score)))
/** 0-1 against the hottest subject, for the bar. */
const share = (t: HeatTag) => (maxScore.value > 0 ? score(t) / maxScore.value : 0)

/** The ten hottest; a picked subject outside them is kept at the end so it stays visible. */
const ranked = computed(() => {
  const sorted = [...allTags.value].filter((t) => score(t) > 0).sort((a, b) => score(b) - score(a) || a.name.localeCompare(b.name))
  const top = sorted.slice(0, TOP)
  const picked = sorted.find((t) => t.slug === props.selected)
  return picked && !top.includes(picked) ? [...top, picked] : top
})

/** Cool to hot, matching the heat bands used elsewhere. */
const barClass = (s: number) => (s > 0.8 ? 'bg-red-600' : s > 0.6 ? 'bg-orange-500' : s > 0.4 ? 'bg-amber-400' : 'bg-amber-200 dark:bg-amber-500/50')
</script>
