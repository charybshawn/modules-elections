<template>
  <div>
    <div class="flex items-start justify-between gap-3">
      <div v-if="detail.kind === 'cell'">
        <p class="font-medium text-gray-900 dark:text-white">{{ detail.candidate.name }} on {{ detail.tag.name }}</p>
        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ detail.cell ? tierShortLabel[detail.cell.tier] ?? detail.cell.tier : 'Nothing on file -- the research hasn\'t found them addressing it.' }}</p>
      </div>
      <div v-else>
        <p class="font-medium text-gray-900 dark:text-white">{{ detail.candidate.name }}</p>
        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">Campaigns on {{ detail.subjects.length }} subject{{ detail.subjects.length === 1 ? '' : 's' }}, strongest first.</p>
      </div>
      <button type="button" class="tap-target-touch -m-1 shrink-0 rounded p-1 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" aria-label="Close details" @click="$emit('close')">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <ul v-if="detail.kind === 'cell' && detail.cell" class="mt-2 list-disc space-y-0.5 pl-5 text-gray-800 dark:text-gray-200">
      <li v-for="p in detail.cell.planks" :key="p">{{ p }}</li>
    </ul>
    <ul v-else-if="detail.kind === 'candidate'" class="mt-2 flex flex-wrap gap-1.5">
      <li v-for="s in detail.subjects" :key="s.slug">
        <Link :href="route('admin.elections.tags.show', s.slug)" :class="tierBadgeClass(s.tier)" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium hover:opacity-80">{{ s.name }}</Link>
      </li>
    </ul>

    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium">
      <Link :href="route('admin.elections.candidates.show', { candidate: detail.candidate.slug, tab: 'platform' })" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ detail.candidate.name }}'s platform &rarr;</Link>
      <Link v-if="detail.kind === 'cell'" :href="route('admin.elections.tags.show', detail.tag.slug)" class="text-indigo-600 hover:underline dark:text-indigo-400">Everyone on {{ detail.tag.name }} &rarr;</Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { tierBadgeClass, tierShortLabel, type CoverageDetailData } from './coverage'

defineProps<{ detail: CoverageDetailData }>()
defineEmits<{ close: [] }>()
</script>
