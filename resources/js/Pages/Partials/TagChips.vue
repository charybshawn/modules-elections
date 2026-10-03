<template>
  <ul v-if="tags.length" class="flex flex-wrap gap-1.5" aria-label="Subjects">
    <li v-for="tag in tags" :key="tag.slug">
      <Link
        :href="route('admin.elections.tags.show', tag.slug)"
        class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20"
      >{{ tag.name }}</Link>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { TagRef } from './types'

/** Subject tags, each linking to its page gathering everything on that subject. */
defineProps<{ tags: TagRef[] }>()
</script>
