<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="City plans" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6" />
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">City plans</h1>
      <p class="mt-1 mb-6 text-sm text-gray-600 dark:text-gray-400">
        The plans council works from, summarized in plain language. Every point links to the page of the City's document it comes from.
      </p>

      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <Link
          v-for="(plan, i) in plans"
          :key="plan.slug"
          :href="route('admin.elections.plans.show', plan.slug)"
          class="group flex flex-col overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10 transition hover:shadow-md hover:ring-indigo-300 dark:hover:ring-indigo-500/50"
        >
          <div :class="['px-6 py-5 text-white bg-gradient-to-br', i % 2 ? 'from-emerald-600 to-teal-600' : 'from-indigo-600 to-sky-600']">
            <p class="text-xs font-semibold uppercase tracking-widest opacity-80">City of Salmon Arm</p>
            <h2 class="mt-1 text-xl font-bold">{{ plan.title }}</h2>
            <p class="mt-1 text-sm opacity-90">{{ plan.status }}</p>
          </div>
          <div class="flex flex-1 flex-col px-6 py-5">
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ plan.short }}</p>
            <dl class="mt-4 grid grid-cols-2 gap-3">
              <div v-for="stat in plan.stats.slice(0, 2)" :key="stat.label">
                <dd class="text-lg font-bold text-gray-900 dark:text-white">{{ stat.value }}</dd>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ stat.label }}</dt>
              </div>
            </dl>
            <span class="mt-5 text-sm font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline">Read the info sheet →</span>
          </div>
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
  plans: { slug: string; title: string; short: string; status: string; stats: { value: string; label: string }[] }[]
}>()
</script>
