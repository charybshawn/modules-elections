<template>
  <article>
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
          <li class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ topics[plank.topic] ?? plank.topic }}</li>
          <li v-if="plank.priority_position" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Their priority #{{ plank.priority_position }}</li>
          <li v-if="plank.has_commitment" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Specific commitment</li>
          <li class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ plank.source_count }} source{{ plank.source_count === 1 ? '' : 's' }}</li>
        </ul>
        <p v-if="plank.rationale" class="mt-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Why it ranks here:</span> {{ plank.rationale }}</p>

        <details v-if="plank.sources.length" class="mt-3 group">
          <summary class="tap-target-touch inline-flex cursor-pointer items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
            What they said ({{ plank.sources.length }})
          </summary>
          <ul class="mt-3 space-y-4 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
            <li v-for="entry in plank.sources" :key="entry.id">
              <EntryCard :entry="entry" :options="options" :editable="editable" :is-new="isNewEntry(entry)" @delete="$emit('delete-entry', entry)" />
            </li>
          </ul>
        </details>

        <button v-if="editable" type="button" class="tap-target-touch mt-2 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', plank)">Delete plank</button>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import EntryCard from './EntryCard.vue'
import { newPillClass } from './classes'
import type { Entry, Options, Plank } from './types'

defineProps<{
  plank: Plank
  /** 1-based position across the whole platform. */
  position: number
  topics: Record<string, string>
  options: Options
  editable?: boolean
  isNew?: boolean
  isNewEntry: (entry: Entry) => boolean
}>()

defineEmits<{ delete: [plank: Plank]; 'delete-entry': [entry: Entry] }>()
</script>
