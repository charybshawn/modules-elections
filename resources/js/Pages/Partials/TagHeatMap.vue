<template>
  <section v-if="heat.headings.length">
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
      <span class="font-medium text-red-700 dark:text-red-400">Heating up this week:</span>
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
    </ul>
  </section>
</template>

<script setup lang="ts">
import { Link, useRemember } from '@inertiajs/vue3'
import { sectionHeadingClass } from './classes'
import type { TagHeat } from './types'

defineProps<{
  heat: TagHeat
  /** The subject currently highlighted on the dashboard. */
  selected: string | null
}>()
defineEmits<{ select: [slug: string | null] }>()

// useRemember hands back a reactive object for object state.
const view = useRemember<{ mode: 'recent' | 'all' }>({ mode: 'recent' }, 'elections-heat-mode') as { mode: 'recent' | 'all' }

type HeatTag = TagHeat['headings'][number]['tags'][number]
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
