<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="City plans" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6" />
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">City plans</h1>
      <p class="mt-1 mb-6 text-sm text-gray-600 dark:text-gray-400">
        The plans council works from, summarized in plain language. Each sheet links to the City's full document, page by page.
      </p>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
          v-for="plan in plans"
          :key="plan.slug"
          :href="route('admin.elections.plans.show', plan.slug)"
          class="block rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-600 transition"
        >
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ plan.title }}</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ plan.short }}</p>
          <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ plan.status }}</p>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

defineProps<{
  plans: { slug: string; title: string; short: string; status: string }[]
}>()
</script>
