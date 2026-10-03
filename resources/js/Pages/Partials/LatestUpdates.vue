<template>
  <section v-if="updates.length" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
    <h2 :class="sectionHeadingClass">Latest updates</h2>

    <TransitionGroup tag="ol" class="mt-3 divide-y divide-gray-100 dark:divide-gray-700" enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition duration-200">
      <li v-for="u in shown" :key="u.id" class="py-2.5 first:pt-0 last:pb-0">
        <!-- A candidate's day of additions. -->
        <Link v-if="u.type === 'candidate'" :href="candidateHref(u)" class="group flex gap-3">
          <CandidatePhoto :name="u.name" :url="u.photo_url" class="h-8 w-8 shrink-0 rounded-full text-xs" />
          <span class="min-w-0 flex-1">
            <span class="flex items-baseline justify-between gap-2">
              <span class="truncate text-sm font-medium text-gray-900 group-hover:underline dark:text-white">{{ u.name }}</span>
              <time :datetime="u.at" :title="formatDateTime(u.at) ?? undefined" class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ ago(u.at) }}</time>
            </span>
            <span class="block text-xs text-gray-600 dark:text-gray-300">{{ u.total }} new: {{ u.counts.join(', ') }}</span>
            <span v-for="title in u.planks" :key="title" class="mt-0.5 block truncate text-xs text-indigo-700 dark:text-indigo-300">New plank: {{ title }}</span>
            <span v-if="u.more_planks" class="block text-xs text-gray-500 dark:text-gray-400">+{{ u.more_planks }} more new planks</span>
          </span>
        </Link>

        <!-- A news story. -->
        <a v-else :href="u.url" target="_blank" rel="noopener noreferrer" class="group flex gap-3">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-baseline justify-between gap-2">
              <span class="line-clamp-2 text-sm font-medium text-gray-900 group-hover:underline dark:text-white">{{ u.title }}</span>
              <time :datetime="u.at" :title="formatDateTime(u.at) ?? undefined" class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ ago(u.at) }}</time>
            </span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">
              {{ [u.outlet, u.candidates ? `${u.candidates} candidate${u.candidates === 1 ? '' : 's'}` : null].filter(Boolean).join(' · ') }}
            </span>
          </span>
        </a>
      </li>
    </TransitionGroup>

    <div v-if="updates.length > PAGE" class="mt-3 flex gap-4 border-t border-gray-100 pt-2 dark:border-gray-700">
      <button v-if="count < updates.length" type="button" class="tap-target-touch inline-flex items-center text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="count += PAGE">
        View more ({{ Math.min(PAGE, updates.length - count) }})
      </button>
      <button v-if="count > PAGE" type="button" class="tap-target-touch inline-flex items-center text-xs font-medium text-gray-500 hover:underline dark:text-gray-400" @click="count = PAGE">Show fewer</button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import CandidatePhoto from './CandidatePhoto.vue'
import { sectionHeadingClass } from './classes'
import { formatDateTime } from './format'
import type { CandidateUpdate, LatestUpdate } from './types'

const props = defineProps<{
  updates: LatestUpdate[]
  /** Server time (Unix seconds), so "ago" doesn't depend on the viewer's clock. */
  now: number
}>()

const PAGE = 10

const candidateHref = (u: CandidateUpdate): string => {
  const params: Record<string, string> = { candidate: u.slug }
  if (u.tab) params.tab = u.tab
  return route('admin.elections.candidates.show', params)
}
const count = ref(PAGE)
const shown = computed(() => props.updates.slice(0, count.value))

/** "just now", "5 min ago", "3 h ago", "yesterday", "4 days ago", then the date. */
const ago = (iso: string) => {
  const seconds = Math.max(0, props.now - Math.floor(new Date(iso).getTime() / 1000))
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} h ago`
  const days = Math.floor(seconds / 86400)
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}
</script>
