<template>
  <img
    v-if="showPhoto"
    :src="url!"
    :alt="name"
    loading="lazy"
    referrerpolicy="no-referrer"
    class="object-cover bg-gray-100 dark:bg-gray-700"
    @error="failed = true"
  />
  <!-- Initials when there's no photo, or the linked one stopped loading
       (social-media image links expire). -->
  <div
    v-else
    class="flex items-center justify-center bg-amber-100 font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
    aria-hidden="true"
  >{{ initials }}</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { isHttpUrl } from './format'

const props = defineProps<{ name: string; url: string | null }>()

const failed = ref(false)
watch(() => props.url, () => (failed.value = false))

// A web address, or a file in the app itself (/images/...).
const isLocal = (url: string | null) => !!url && /^\/[^/\s]/.test(url)
const showPhoto = computed(() => (isHttpUrl(props.url) || isLocal(props.url)) && !failed.value)
const initials = computed(() =>
  props.name.split(/\s+/).filter(Boolean).map((part) => part[0]).slice(0, 2).join('').toUpperCase(),
)
</script>
