<template>
  <!-- A candidate's photo with their party on it: a coloured ring and, on cards, a
       chip straddling the bottom edge; on the profile, a full-width ribbon with
       the party's name. Without a party it's just the photo. -->
  <CandidatePhoto v-if="!style" :name="name" :url="url" :class="photoClass" />

  <div v-else-if="variant === 'ribbon'" class="relative shrink-0 overflow-hidden border-4" :class="[photoClass, style.border]">
    <CandidatePhoto :name="name" :url="url" class="h-full w-full" />
    <span :class="style.solid" class="absolute inset-x-0 bottom-0 truncate px-1 py-1 text-center text-[11px] font-bold uppercase leading-none tracking-wide" :title="style.label">{{ style.label }}</span>
  </div>

  <div v-else class="relative shrink-0 pb-2">
    <CandidatePhoto :name="name" :url="url" :class="[photoClass, 'border-4', style.border]" />
    <span :class="style.solid" class="absolute inset-x-0 bottom-0 mx-auto w-fit rounded-full px-2 py-0.5 text-[10px] font-extrabold leading-none tracking-wide shadow ring-2 ring-white dark:ring-gray-800" :title="style.label">{{ style.short }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CandidatePhoto from './CandidatePhoto.vue'
import { partyStyle } from './party'

const props = defineProps<{
  name: string
  url: string | null
  party?: string | null
  /** chip: a small party chip on the photo's bottom edge (cards); ribbon: the party name across its foot (profile). */
  variant?: 'chip' | 'ribbon'
  /** Size and shape of the photo, e.g. "h-14 w-14 rounded-full text-lg". */
  photoClass: string
}>()

const style = computed(() => partyStyle(props.party))
</script>
