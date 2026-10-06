<template>
  <!-- The module's own sections. They share the one Elections sidebar entry
       (and its viewer permission), so they're tabs here rather than sidebar
       items. -->
  <div class="flex items-center gap-3">
    <nav
      class="flex min-w-0 flex-1 gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1 ring-1 ring-inset ring-gray-900/5 scrollbar-hide dark:bg-gray-800/80 dark:ring-white/10"
      aria-label="Elections sections"
    >
      <Link
        v-for="section in sections"
        :key="section.name"
        :href="route(section.name)"
        :aria-current="section.active ? 'page' : undefined"
        :class="[
          'tap-target-touch group relative inline-flex flex-none items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium whitespace-nowrap transition-all duration-150',
          // Something new: filled orange (the open one is a little deeper). Open: a raised white tab.
          section.unread
            ? [section.active ? 'bg-orange-600 shadow-md' : 'bg-orange-500 shadow-sm hover:bg-orange-600', 'text-white']
            : section.active
              ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-700 dark:text-white dark:ring-white/10'
              : 'text-gray-600 hover:bg-white/70 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-100',
        ]"
      >
        <svg
          class="h-4 w-4 shrink-0"
          :class="section.unread ? 'text-white' : section.active ? 'text-indigo-500 dark:text-indigo-300' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-500'"
          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
        >
          <path :d="section.icon" />
        </svg>
        {{ section.title }}
        <span v-if="section.unread" class="rounded-full bg-white px-1.5 py-0.5 text-[11px] font-bold uppercase leading-none tracking-wide text-orange-700">new</span>
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

// Outline icons (Heroicons, MIT) so each tab reads at a glance.
const icons = {
  candidates: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
  subjects: 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z',
  pulse: 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155',
  plans: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
}

const sections = computed(() => [
  { name: 'admin.elections.index', title: 'Candidates', icon: icons.candidates, active: route().current('admin.elections.index') || route().current('admin.elections.candidates.*') || route().current('admin.elections.compare'), unread: updates.candidatesUnread().items + updates.sectionUnread('events') > 0 },
  { name: 'admin.elections.tags.index', title: 'Browse by subject', icon: icons.subjects, active: route().current('admin.elections.tags.*'), unread: updates.sectionUnread('subjects') > 0 },
  { name: 'admin.elections.pulse.index', title: 'Community Pulse', icon: icons.pulse, active: route().current('admin.elections.pulse.*'), unread: updates.sectionUnread('pulse') > 0 },
  { name: 'admin.elections.plans.index', title: 'City Plans + Finances', icon: icons.plans, active: route().current('admin.elections.plans.*') || route().current('admin.elections.finances'), unread: updates.plansUnread() > 0 || updates.financesUnread() },
])
</script>
