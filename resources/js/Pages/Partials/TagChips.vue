<template>
  <ul v-if="tags.length" class="flex flex-wrap gap-1.5" aria-label="Subjects">
    <li v-for="tag in tags" :key="tag.slug">
      <!-- Filter mode (e.g. the Platform tab): narrows the list in place. -->
      <button
        v-if="asFilter"
        type="button"
        :aria-pressed="active === tag.slug"
        :class="active === tag.slug ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20'"
        class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium transition-colors"
        @click="$emit('select', active === tag.slug ? null : tag.slug)"
      >{{ tag.name }}</button>
      <Link
        v-else
        :href="route('admin.elections.tags.show', tag.slug)"
        class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20"
      >{{ tag.name }}</Link>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { TagRef } from './types'

/**
 * Subject tags. By default each links to its subject page; with asFilter
 * they toggle a filter on the current list instead.
 */
withDefaults(defineProps<{ tags: TagRef[]; asFilter?: boolean; active?: string | null }>(), { asFilter: false, active: null })
defineEmits<{ select: [slug: string | null] }>()
</script>
