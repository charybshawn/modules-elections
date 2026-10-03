<template>
  <div>
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <div class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-400">{{ options.eventKinds[event.kind] ?? event.kind }}</div>
        <div class="mt-0.5 font-medium text-gray-900 dark:text-white">
          <a v-if="isHttpUrl(event.url)" :href="event.url" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ event.title }}</a>
          <template v-else>{{ event.title }}</template>
        </div>
      </div>
    </div>
    <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
      {{ formatDateTime(event.starts_at) }}<template v-if="event.ends_at"> – {{ endLabel }}</template>
    </div>
    <div v-if="event.location" class="text-sm text-gray-500 dark:text-gray-400">{{ event.location }}</div>
    <p v-if="event.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400 whitespace-pre-line">{{ event.description }}</p>
    <div v-if="!readOnly" class="mt-2 flex gap-3 text-xs">
      <button type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete', event)">Delete</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatDateTime, isHttpUrl } from './format'
import type { ElectionEvent, Options } from './types'

const props = defineProps<{ event: ElectionEvent; options: Options; readOnly: boolean }>()
defineEmits<{ delete: [event: ElectionEvent] }>()

// Same day: just the end time. Multi-day (advance voting): the full date.
const endLabel = computed(() => {
  const end = props.event.ends_at!
  if (end.slice(0, 10) === props.event.starts_at.slice(0, 10)) {
    const [hh, mm] = end.slice(11, 16).split(':').map(Number)
    return new Date(2000, 0, 1, hh, mm).toLocaleTimeString('en-CA', { hour: 'numeric', minute: '2-digit' })
  }
  return formatDateTime(end)
})
</script>
