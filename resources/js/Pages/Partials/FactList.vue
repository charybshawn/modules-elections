<template>
  <!-- Background facts as a plain bulleted list, each with its source under it. -->
  <ul class="space-y-3">
    <li v-for="entry in entries" :key="entry.id" class="flex gap-3">
      <span class="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />
      <div class="min-w-0">
        <p class="text-sm sm:text-base leading-relaxed text-gray-900 dark:text-gray-100">
          {{ entry.summary }}
          <span v-if="isNew(entry)" :class="newPillClass" class="ml-1 align-middle">New</span>
        </p>
        <p v-if="entry.quote" class="mt-1 border-l-2 border-amber-400 pl-3 text-sm italic text-gray-600 dark:border-amber-500 dark:text-gray-300">&ldquo;{{ entry.quote }}&rdquo;</p>
        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
          <a
            v-if="isHttpUrl(entry.source_url)"
            :href="entry.source_url"
            target="_blank"
            rel="noopener noreferrer"
            class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white"
          >{{ entry.source_name ?? hostOf(entry.source_url) }}</a>
          <span v-if="entry.published_on">{{ formatDate(entry.published_on) }}</span>
          <button v-if="editable" type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', entry)">Delete</button>
        </p>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import type { Entry } from './types'
import { newPillClass } from './classes'
import { formatDate, hostOf, isHttpUrl } from './format'

defineProps<{
  entries: Entry[]
  editable?: boolean
  isNew: (entry: Entry) => boolean
}>()

defineEmits<{ delete: [entry: Entry] }>()
</script>
