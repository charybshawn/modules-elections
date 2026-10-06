<template>
  <!-- "p. 15" / "pp. 9, 14": each number opens the City's PDF at that page. -->
  <span v-if="pages.length" class="whitespace-nowrap text-[11px] font-medium text-gray-400 dark:text-gray-500">
    {{ pages.length === 1 ? 'p.' : 'pp.' }}
    <template v-for="(p, i) in pages" :key="p"><a
      :href="`${url}#page=${p}`"
      target="_blank"
      rel="noopener"
      :title="`Open page ${p} of the City's document`"
      class="text-indigo-500 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
    >{{ p }}</a><template v-if="i < pages.length - 1">, </template></template>
  </span>
</template>

<script setup lang="ts">
defineProps<{ pages: number[]; url: string }>()
</script>
