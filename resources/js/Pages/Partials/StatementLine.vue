<template>
  <!-- One statement, compact: the quote (or summary) clipped to three lines
       and its source; tap to see the full statement. -->
  <div>
    <button v-if="!open" type="button" class="group block w-full text-left" :aria-expanded="false" @click="open = true">
      <span class="line-clamp-3 text-sm text-gray-800 group-hover:text-gray-950 dark:text-gray-200 dark:group-hover:text-white" :class="entry.quote ? 'italic' : ''">
        {{ entry.quote ? `“${entry.quote}”` : entry.summary }}
      </span>
      <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
        <span v-if="isNew" class="rounded-full bg-orange-500 px-1.5 font-semibold text-white dark:bg-orange-600">New</span>
        <span>{{ entry.source_name ?? hostOf(entry.source_url) }}</span>
        <span v-if="entry.published_on">· {{ formatDate(entry.published_on) }}</span>
        <span class="text-indigo-600 group-hover:underline dark:text-indigo-400">Full statement</span>
      </span>
    </button>
    <div v-else>
      <EntryCard :entry="entry" :options="options" :editable="editable" :is-new="isNew" @delete="$emit('delete', entry)" />
      <button type="button" class="tap-target-touch mt-1 text-xs font-medium text-gray-500 hover:underline dark:text-gray-400" @click="open = false">Show less</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import EntryCard from './EntryCard.vue'
import { formatDate, hostOf } from './format'
import type { Entry, Options } from './types'

defineProps<{ entry: Entry; options: Options; editable?: boolean; isNew?: boolean }>()
defineEmits<{ delete: [entry: Entry] }>()

const open = ref(false)
</script>
