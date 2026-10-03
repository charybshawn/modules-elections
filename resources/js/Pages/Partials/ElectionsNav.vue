<template>
  <!-- The module's own sections. They share the one Elections sidebar entry
       (and its viewer permission), so they're tabs here rather than sidebar
       items. -->
  <nav class="flex gap-6 overflow-x-auto scrollbar-hide border-b border-gray-200 dark:border-gray-700" aria-label="Elections sections">
    <Link
      v-for="section in sections"
      :key="section.name"
      :href="route(section.name)"
      :aria-current="section.active ? 'page' : undefined"
      :class="[
        'tap-target-touch -mb-px inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors',
        section.active
          ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
          : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
      ]"
    >{{ section.title }}</Link>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const sections = computed(() => [
  { name: 'admin.elections.index', title: 'Candidates', active: route().current('admin.elections.index') || route().current('admin.elections.candidates.*') },
  { name: 'admin.elections.tags.index', title: 'Browse by subject', active: route().current('admin.elections.tags.*') },
  { name: 'admin.elections.pulse.index', title: 'Community Pulse', active: route().current('admin.elections.pulse.*') },
])
</script>
