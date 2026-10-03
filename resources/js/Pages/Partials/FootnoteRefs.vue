<template>
  <sup v-if="sources.length" class="ml-0.5 text-[10px] font-medium text-violet-700 dark:text-violet-300"><template v-for="(url, j) in sources" :key="url"><a :href="`#${anchor(url)}`" class="hover:underline">{{ number(url) }}</a><template v-if="j < sources.length - 1">,</template></template></sup>
</template>

<script setup lang="ts">
/** Superscript footnote numbers for a point, each linking to its entry in the numbered source list. */
defineProps<{
  sources: string[]
  number: (url: string) => number
  anchor: (url: string) => string
}>()
</script>
