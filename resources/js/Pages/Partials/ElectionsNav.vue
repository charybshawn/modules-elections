<template>
  <!-- The module's own sections. They share the one Elections sidebar entry
       (and its viewer permission), so they're tabs here rather than sidebar
       items. -->
  <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700">
  <nav class="flex min-w-0 flex-1 gap-1 overflow-x-auto scrollbar-hide" aria-label="Elections sections">
    <Link
      v-for="section in sections"
      :key="section.name"
      :href="route(section.name)"
      :aria-current="section.active ? 'page' : undefined"
      :class="[
        'tap-target-touch -mb-px inline-flex items-center gap-1.5 rounded-t-md py-3 px-3 border-b-2 font-medium text-sm whitespace-nowrap transition-colors',
        // A section with something new is filled orange; the open one keeps a darker underline.
        section.unread
          ? [section.active ? 'border-orange-800' : 'border-orange-500', 'bg-orange-500 text-white hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-500']
          : section.active
            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
      ]"
    >
      {{ section.title }}
      <span v-if="section.unread" class="rounded-full bg-white px-1.5 py-0.5 text-xs font-semibold leading-none text-orange-700">new</span>
    </Link>
  </nav>
  <QuickSearch />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import QuickSearch from './QuickSearch.vue'
import { useUpdates } from './updates'

// Each section lights up when it has something the viewer hasn't looked at.
// Candidates counts every candidate tab plus the events list; Browse by subject,
// Community Pulse and each City plan sheet are marked seen when opened.
const updates = useUpdates()
onMounted(() => updates.ensure())

const sections = computed(() => [
  { name: 'admin.elections.index', title: 'Candidates', active: route().current('admin.elections.index') || route().current('admin.elections.candidates.*') || route().current('admin.elections.compare'), unread: updates.candidatesUnread().items + updates.sectionUnread('events') > 0 },
  { name: 'admin.elections.tags.index', title: 'Browse by subject', active: route().current('admin.elections.tags.*'), unread: updates.sectionUnread('subjects') > 0 },
  { name: 'admin.elections.pulse.index', title: 'Community Pulse', active: route().current('admin.elections.pulse.*'), unread: updates.sectionUnread('pulse') > 0 },
  { name: 'admin.elections.plans.index', title: 'City Plans + Finances', active: route().current('admin.elections.plans.*') || route().current('admin.elections.finances'), unread: updates.plansUnread() > 0 || updates.financesUnread() },
])
</script>
