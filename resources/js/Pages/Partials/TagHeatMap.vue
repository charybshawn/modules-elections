<template>
  <section v-if="heat.headings.length">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h2 :class="sectionHeadingClass">What's being talked about</h2>
      <Link :href="route('admin.elections.tags.index')" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">All subjects &rarr;</Link>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      Subjects shaded by how often candidates, the news and residents have raised them, with recent mentions counting most (a mention's weight halves every {{ heat.halfLifeDays }} days). Scorecard answers aren't counted.
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
          <Link
            v-for="tag in heading.tags"
            :key="tag.slug"
            :href="route('admin.elections.tags.show', tag.slug)"
            :title="`${tag.mentions} mention${tag.mentions === 1 ? '' : 's'}, ${tag.recent} in the last two weeks`"
            :class="levelClass[tag.level]"
            class="rounded px-2 py-1 text-sm leading-none transition-opacity hover:opacity-80"
          >{{ tag.name }}</Link>
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
import { Link } from '@inertiajs/vue3'
import { sectionHeadingClass } from './classes'
import type { TagHeat } from './types'

defineProps<{ heat: TagHeat }>()

/** Five bands, cool grey to hot red; text weight rises with them so it reads without colour too. */
const levelClass: Record<number, string> = {
  0: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
  1: 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
  2: 'bg-amber-200 text-amber-900 font-medium dark:bg-amber-500/30 dark:text-amber-100',
  3: 'bg-orange-400 text-white font-semibold dark:bg-orange-500/80',
  4: 'bg-red-600 text-white font-bold dark:bg-red-600/90',
}
</script>
