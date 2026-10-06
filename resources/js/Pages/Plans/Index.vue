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
          class="group flex flex-col overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm transition hover:shadow-md"
          :class="updates.planUnread(plan.slug) ? 'ring-4 ring-orange-400 dark:ring-orange-500' : 'ring-1 ring-gray-900/5 dark:ring-white/10 hover:ring-indigo-300 dark:hover:ring-indigo-500/50'"
        >
          <div :class="['px-6 py-5 text-white bg-gradient-to-br', i % 2 ? 'from-emerald-600 to-teal-600' : 'from-indigo-600 to-sky-600']">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest opacity-80">
              City of Salmon Arm
              <span v-if="updates.planUnread(plan.slug)" class="rounded-full bg-orange-500 px-2 py-0.5 text-xs font-semibold normal-case tracking-normal text-white opacity-100">Updated</span>
            </p>
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

        <Link
          v-for="item in progress"
          :key="item.slug"
          :href="route('admin.elections.plans.progress', item.slug)"
          class="group flex flex-col overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm transition hover:shadow-md md:col-span-2"
          :class="updates.planUnread(item.slug) ? 'ring-4 ring-orange-400 dark:ring-orange-500' : 'ring-1 ring-gray-900/5 dark:ring-white/10 hover:ring-emerald-300 dark:hover:ring-emerald-500/50'"
        >
          <div class="bg-gradient-to-br from-amber-600 to-rose-600 px-6 py-5 text-white">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest opacity-80">
              Scoresheet
              <span v-if="updates.planUnread(item.slug)" class="rounded-full bg-orange-500 px-2 py-0.5 text-xs font-semibold normal-case tracking-normal text-white opacity-100">Updated</span>
            </p>
            <h2 class="mt-1 text-xl font-bold">{{ item.title }}</h2>
            <p class="mt-1 text-sm opacity-90">{{ item.status }}</p>
          </div>
          <div class="px-6 py-5">
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ item.short }}</p>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
              {{ item.tally.underway }} under way · {{ item.tally.planning }} planning · {{ item.tally.paused }} paused · {{ item.tally.unknown }} with no report found · {{ item.tally.complete }} complete
            </p>
            <span class="mt-4 inline-block text-sm font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline">Open the scoresheet →</span>
          </div>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { useUpdates } from '../Partials/updates'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

// A sheet refreshed since you last opened it carries an orange "Updated" chip.
const updates = useUpdates()
onMounted(() => updates.ensure())

defineProps<{
  progress: { slug: string; title: string; short: string; status: string; tally: Record<string, number> }[]
  plans: { slug: string; title: string; short: string; status: string; stats: { value: string; label: string }[] }[]
}>()
</script>
