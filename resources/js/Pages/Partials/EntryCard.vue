<template>
  <article class="group relative">
    <p v-if="entry.question" class="text-sm text-gray-500 dark:text-gray-400">
      <span class="font-medium text-gray-700 dark:text-gray-300">Asked:</span> {{ entry.question }}
    </p>

    <blockquote
      v-if="entry.quote"
      class="mt-1 border-l-2 border-amber-400 pl-3 text-base italic leading-relaxed text-gray-900 dark:border-amber-500 dark:text-gray-100 whitespace-pre-line"
    >&ldquo;{{ entry.quote }}&rdquo;</blockquote>

    <p
      class="text-sm leading-relaxed"
      :class="entry.quote ? 'mt-2 text-gray-600 dark:text-gray-400' : 'mt-1 text-gray-900 dark:text-gray-100'"
    >{{ entry.summary }}</p>

    <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
      <span v-if="isNew" class="rounded-full bg-orange-500 px-1.5 py-0.5 font-semibold text-white dark:bg-orange-600">New</span>
      <span v-if="showKind" class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ options.kinds[entry.kind] ?? entry.kind }}</span>
      <a
        v-if="isHttpUrl(entry.source_url)"
        :href="entry.source_url"
        target="_blank"
        rel="noopener noreferrer"
        class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white"
      >{{ entry.source_name ?? hostOf(entry.source_url) }}</a>
      <span>{{ options.sourceTypes[entry.source_type] ?? entry.source_type }}</span>
      <span v-if="entry.published_on">· {{ formatDate(entry.published_on) }}</span>
      <span v-else class="text-amber-600 dark:text-amber-400">· undated</span>
    </p>
    <TagChips v-if="entry.tags?.length" :tags="entry.tags" class="mt-1.5" />

    <div v-if="editable" class="mt-1 flex gap-3 text-xs">
      <button type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', entry)">Delete</button>
    </div>
  </article>
</template>

<script setup lang="ts">
import TagChips from './TagChips.vue'
import type { Entry, Options } from './types'
import { formatDate, hostOf, isHttpUrl } from './format'

defineProps<{
  entry: Entry
  options: Options
  editable?: boolean
  /** Label the entry's kind -- for sections that mix kinds (statements + Q&A). */
  showKind?: boolean
  /** Added since the viewer last opened this candidate. */
  isNew?: boolean
}>()

defineEmits<{ delete: [entry: Entry] }>()
</script>
