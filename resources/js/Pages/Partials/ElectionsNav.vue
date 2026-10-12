<template>
  <!-- The module's own sections. They share the one Elections sidebar entry
       (and its viewer permission), so they're tabs here rather than sidebar
       items. -->
  <div class="flex items-center justify-end gap-3 md:justify-start">
    <nav
      class="hidden min-w-0 flex-1 gap-1 overflow-x-auto rounded-xl md:flex bg-gray-100 p-1 ring-1 ring-inset ring-gray-900/5 scrollbar-hide dark:bg-gray-800/80 dark:ring-white/10"
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

    <!-- Phones: the sections as always-visible buttons in a fixed bottom bar,
         dressed like the other admin pages' bottom drawers. The search stays up top. -->
    <nav
      :style="{ gridTemplateColumns: `repeat(${sections.length}, minmax(0, 1fr))` }"
      class="fixed inset-x-0 bottom-0 z-40 grid border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[inset_0_1px_0_rgba(255,255,255,0.6),0_-4px_14px_rgba(0,0,0,0.18)] md:hidden dark:border-gray-600 dark:bg-gray-700 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_-4px_14px_rgba(0,0,0,0.45)]"
      aria-label="Elections sections"
    >
      <Link
        v-for="section in sections"
        :key="section.name"
        :href="route(section.name)"
        :aria-current="section.active ? 'page' : undefined"
        :class="[
          'tap-target-touch relative flex min-w-0 flex-col items-center justify-center gap-1.5 px-1 pt-3.5 pb-3.5 text-[11px] font-medium leading-tight',
          section.active ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400',
        ]"
      >
        <span class="relative">
          <svg :class="[section.tone, 'h-6 w-6']" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path v-for="d in section.solid" :key="d" fill-rule="evenodd" clip-rule="evenodd" :d="d" />
          </svg>
          <span v-if="section.unread" class="absolute -right-1 -top-0.5 h-2.5 w-2.5 rounded-full bg-orange-500 ring-2 ring-white dark:ring-gray-700" aria-hidden="true" />
        </span>
        <span class="max-w-full truncate">{{ section.short }}</span>
        <span v-if="section.unread" class="sr-only">(new)</span>
        <span v-if="section.active" class="absolute bottom-0 left-1/2 h-0.5 w-1/2 -translate-x-1/2 rounded-full bg-indigo-600 dark:bg-indigo-400" />
      </Link>
    </nav>
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

// The same icons, filled in, for the phone bar (Heroicons solid, MIT).
const solid = {
  candidates: [
    'M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z',
    'M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.486l-.115.04c-.567.2-1.156.349-1.764.441Z',
  ],
  subjects: ['M5.25 2.25a3 3 0 0 0-3 3v4.318a3 3 0 0 0 .879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 0 0 5.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 0 0-2.122-.879H5.25ZM6.375 7.5a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z'],
  pulse: [
    'M4.913 2.658c2.075-.27 4.19-.408 6.337-.408 2.147 0 4.262.139 6.337.408 1.922.25 3.291 1.861 3.405 3.727a4.403 4.403 0 0 0-1.032-.211 50.89 50.89 0 0 0-8.42 0c-2.358.196-4.04 2.19-4.04 4.434v4.286a4.47 4.47 0 0 0 2.433 3.984L7.28 21.53A.75.75 0 0 1 6 21v-4.03a48.527 48.527 0 0 1-1.087-.128C2.905 16.58 1.5 14.833 1.5 12.862V6.638c0-1.97 1.405-3.718 3.413-3.979Z',
    'M15.75 7.5c-1.376 0-2.739.057-4.086.169C10.124 7.797 9 9.103 9 10.609v4.285c0 1.507 1.128 2.814 2.67 2.94 1.243.102 2.5.157 3.768.165l2.782 2.781a.75.75 0 0 0 1.28-.53v-2.39l.33-.026c1.542-.125 2.67-1.433 2.67-2.94v-4.286c0-1.505-1.125-2.811-2.664-2.94A49.392 49.392 0 0 0 15.75 7.5Z',
  ],
  plans: ['M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75ZM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 0 1-1.875-1.875V8.625ZM3 13.125c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v6.75c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 0 1 3 19.875v-6.75Z'],
}

const sections = computed(() => [
  { name: 'admin.elections.index', title: 'Candidates', short: 'Candidates', tone: 'text-indigo-500 dark:text-indigo-400', icon: icons.candidates, solid: solid.candidates, active: route().current('admin.elections.index') || route().current('admin.elections.candidates.*') || route().current('admin.elections.compare'), unread: updates.candidatesUnread().items + updates.sectionUnread('events') > 0 },
  { name: 'admin.elections.tags.index', title: 'Browse by subject', short: 'Subjects', tone: 'text-amber-500 dark:text-amber-400', icon: icons.subjects, solid: solid.subjects, active: route().current('admin.elections.tags.*'), unread: updates.sectionUnread('subjects') > 0 },
  { name: 'admin.elections.pulse.index', title: 'Community Pulse', short: 'Pulse', tone: 'text-rose-500 dark:text-rose-400', icon: icons.pulse, solid: solid.pulse, active: route().current('admin.elections.pulse.*'), unread: updates.sectionUnread('pulse') > 0 },
  { name: 'admin.elections.plans.index', title: 'City Plans + Finances', short: 'City Plans', tone: 'text-emerald-600 dark:text-emerald-400', icon: icons.plans, solid: solid.plans, active: route().current('admin.elections.plans.*') || route().current('admin.elections.finances') || route().current('admin.elections.grants'), unread: updates.plansUnread() > 0 || updates.financesUnread() || updates.grantsUnread() },
])


</script>
