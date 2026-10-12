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
    <QuickSearch election="bc-provincial-salmon-arm-shuswap" />

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
import QuickSearch from '../Partials/QuickSearch.vue'
import { useUpdates } from '../Partials/updates'

// The BC provincial election's own section menu: its candidates, and the way back
// to the hub. (The municipal election has its own nav: Partials/ElectionsNav.vue.)
const updates = useUpdates('bc-provincial-salmon-arm-shuswap')
onMounted(() => updates.ensure())

const icons = {
  candidates: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
  home: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
}
const solid = {
  candidates: [
    'M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75c-2.305 0-4.47-.612-6.337-1.684a.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z',
    'M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.486l-.115.04c-.567.2-1.156.349-1.764.441Z',
  ],
  home: ['M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z', 'M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z'],
}

const sections = computed(() => [
  { name: 'admin.elections.provincial', title: 'Candidates', short: 'Candidates', tone: 'text-indigo-500 dark:text-indigo-400', icon: icons.candidates, solid: solid.candidates, active: true, unread: updates.candidatesUnread().items + updates.sectionUnread('events') > 0 },
  { name: 'admin.elections.home', title: 'All elections', short: 'Elections', tone: 'text-amber-500 dark:text-amber-400', icon: icons.home, solid: solid.home, active: false, unread: false },
])
</script>
