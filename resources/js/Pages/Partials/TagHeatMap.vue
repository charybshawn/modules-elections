<template>
  <section v-if="heat.headings.length">
    <!-- Collapsed: once a subject is picked, the map shrinks to one strip so
         the candidate cards get the screen. -->
    <div v-if="collapsed" key="strip" class="rounded-lg bg-white p-2 shadow-sm dark:bg-gray-800">
      <div class="flex items-center gap-2">
        <div class="-my-1 flex min-w-0 flex-1 gap-1.5 overflow-x-auto py-1 pl-0.5 scrollbar-hide" role="group" aria-label="Switch subject">
          <button
            v-for="tag in stripTags"
            :key="tag.slug"
            type="button"
            :aria-pressed="selected === tag.slug"
            :class="[levelClass[view.mode === 'recent' ? tag.level : tag.level_all], selected === tag.slug ? 'ring-2 ring-gray-900 dark:ring-white' : 'opacity-70 hover:opacity-100']"
            class="tap-target-touch shrink-0 whitespace-nowrap rounded px-2 py-1.5 text-sm leading-none transition"
            @click="$emit('select', selected === tag.slug ? null : tag.slug)"
          >{{ tag.name }}</button>
        </div>
        <button
          type="button"
          class="tap-target-touch inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          :aria-expanded="false"
          @click="expanded = true"
        >
          <span class="hidden sm:inline">All subjects</span>
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          <span class="sr-only sm:hidden">Show all subjects</span>
        </button>
      </div>
      <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 px-1 text-xs text-gray-600 dark:text-gray-300">
        <span><span class="font-semibold text-gray-900 dark:text-white">{{ matchingCount }}</span> candidate{{ matchingCount === 1 ? '' : 's' }} campaign{{ matchingCount === 1 ? 's' : '' }} on <span class="font-semibold text-gray-900 dark:text-white">{{ selectedName }}</span>, listed first</span>
        <span class="inline-flex items-center gap-3">
          <Link v-if="selected" :href="route('admin.elections.tags.show', selected)" class="tap-target-touch inline-flex items-center font-medium text-indigo-600 underline underline-offset-2 dark:text-indigo-400">Open subject</Link>
          <button type="button" class="tap-target-touch inline-flex items-center font-medium underline underline-offset-2" @click="$emit('select', null)">Clear</button>
        </span>
      </p>
    </div>

    <div v-else key="full">
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
      <h2 :class="sectionHeadingClass">What's being talked about</h2>
      <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-700" role="group" aria-label="Heat by">
        <button
          v-for="m in (['recent', 'all'] as const)"
          :key="m"
          type="button"
          :aria-pressed="view.mode === m"
          :class="view.mode === m ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'"
          class="tap-target-touch rounded-md px-3 py-1 text-xs font-medium transition"
          @click="view.mode = m"
        >{{ m === 'recent' ? 'Recent' : 'All-time' }}</button>
      </div>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      <template v-if="view.mode === 'recent'">Shaded by how often candidates, the news and residents raise each subject, recent mentions counting most (a mention's weight halves every {{ heat.halfLifeDays }} days).</template>
      <template v-else>Shaded by total mentions over the whole campaign.</template>
      Scorecard answers aren't counted. Tap a subject to see who campaigns on it.
    </p>

    <p v-if="heat.heatingUp.length" class="mt-3 text-sm text-gray-700 dark:text-gray-300">
      <span class="font-medium text-red-700 dark:text-red-400">Heating up this week:</span>{{ ' ' }}
      <template v-for="(t, i) in heat.heatingUp" :key="t.slug">
        <Link :href="route('admin.elections.tags.show', t.slug)" class="hover:underline">{{ t.name }}</Link><template v-if="i < heat.heatingUp.length - 1"> · </template>
      </template>
    </p>

    <dl class="mt-4 space-y-3 rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
      <div v-for="heading in heat.headings" :key="heading.topic" class="sm:flex sm:gap-4">
        <dt class="shrink-0 pt-1 text-xs font-medium uppercase tracking-wide text-gray-500 sm:w-40 dark:text-gray-400">{{ heading.title }}</dt>
        <dd class="mt-1 flex flex-wrap gap-1.5 sm:mt-0">
          <button
            v-for="tag in sortedTags(heading.tags)"
            :key="tag.slug"
            type="button"
            :aria-pressed="selected === tag.slug"
            :title="`${tag.mentions} mention${tag.mentions === 1 ? '' : 's'}, ${tag.recent} in the last two weeks`"
            :class="[levelClass[view.mode === 'recent' ? tag.level : tag.level_all], selected === tag.slug ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white dark:ring-offset-gray-800' : selected ? 'opacity-50' : '']"
            class="rounded px-2 py-1.5 text-sm leading-none transition hover:opacity-80"
            @click="$emit('select', selected === tag.slug ? null : tag.slug)"
          >{{ tag.name }}</button>
        </dd>
      </div>
    </dl>

    <ul class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400" aria-label="Legend">
      <li>Cooler</li>
      <li v-for="level in [0, 1, 2, 3, 4]" :key="level"><span :class="levelClass[level]" class="inline-block h-3 w-6 rounded" /></li>
      <li>Hotter</li>
      <li v-if="selected" class="ml-auto">
        <button type="button" class="tap-target-touch inline-flex items-center gap-1 font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300" @click="expanded = false">
          Collapse
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
        </button>
      </li>
    </ul>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { Link, useRemember } from '@inertiajs/vue3'
import { sectionHeadingClass } from './classes'
import type { TagHeat } from './types'

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

// The full map shows until a subject is picked; then it folds to a strip
// (until the viewer opens it again).
const expanded = ref(props.selected === null)
watch(
  () => props.selected,
  (slug) => {
    expanded.value = slug === null
  },
)
const collapsed = computed(() => props.selected !== null && !expanded.value)

type HeatTag = TagHeat['headings'][number]['tags'][number]
const allTags = computed(() => props.heat.headings.flatMap((h) => h.tags))
const selectedName = computed(() => allTags.value.find((t) => t.slug === props.selected)?.name ?? '')
/** The strip: the picked subject first, then the hottest others to switch to. */
const stripTags = computed(() => {
  const hottest = [...allTags.value].sort((a, b) => (view.mode === 'recent' ? b.heat - a.heat : b.mentions - a.mentions))
  const picked = hottest.find((t) => t.slug === props.selected)
  return [...(picked ? [picked] : []), ...hottest.filter((t) => t.slug !== props.selected).slice(0, 14)]
})
/** Within a heading, hottest first by whichever measure is showing. */
const sortedTags = (tags: HeatTag[]) =>
  [...tags].sort((a, b) => (view.mode === 'recent' ? b.heat - a.heat : b.mentions - a.mentions) || a.name.localeCompare(b.name))

/** Five bands, cool grey to hot red; text weight rises with them so it reads without colour too. */
const levelClass: Record<number, string> = {
  0: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
  1: 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
  2: 'bg-amber-200 text-amber-900 font-medium dark:bg-amber-500/30 dark:text-amber-100',
  3: 'bg-orange-400 text-white font-semibold dark:bg-orange-500/80',
  4: 'bg-red-600 text-white font-bold dark:bg-red-600/90',
}
</script>
