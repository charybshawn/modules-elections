<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader :title="plan.title" :href="route('admin.elections.plans.index')" />

    <div class="px-4 sm:px-0 max-w-3xl mx-auto">
      <ElectionsNav class="mb-6 print:hidden" />
      <Link :href="route('admin.elections.plans.index')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm mb-4 print:hidden">← City plans</Link>

      <article class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 sm:p-8 print:shadow-none print:p-0">
        <header class="border-b border-gray-200 dark:border-gray-700 pb-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">City of Salmon Arm</p>
          <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ plan.title }}</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ plan.status }}</p>
          <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <a :href="plan.source_url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ plan.source_label }} ↗</a>
            <a :href="plan.page_url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">City web page ↗</a>
            <button type="button" class="tap-target-touch text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white print:hidden" @click="print">Print</button>
          </div>
        </header>

        <div class="mt-5 space-y-3 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
          <p v-for="(para, i) in plan.summary" :key="i">{{ para }}</p>
        </div>

        <section v-for="section in plan.sections" :key="section.heading" class="mt-6 break-inside-avoid">
          <div class="flex items-baseline justify-between gap-3">
            <h2 :class="sectionHeadingClass">{{ section.heading }}</h2>
            <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
              {{ section.pages.length === 1 ? 'p.' : 'pp.' }}
              <template v-for="(p, i) in section.pages" :key="p"><a :href="`${plan.source_url}#page=${p}`" target="_blank" rel="noopener" class="hover:underline">{{ p }}</a><template v-if="i < section.pages.length - 1">, </template></template>
            </p>
          </div>
          <p v-if="section.text" class="mt-2 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ section.text }}</p>
          <ul v-if="section.items" class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-800 dark:text-gray-200">
            <li v-for="item in section.items" :key="item">{{ item }}</li>
          </ul>
          <div v-for="group in section.groups ?? []" :key="group.label" class="mt-3">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ group.label }}</h3>
            <ul class="mt-1 space-y-1 text-sm text-gray-800 dark:text-gray-200">
              <li v-for="item in group.items" :key="item.text" class="flex items-baseline justify-between gap-3 border-b border-gray-100 dark:border-gray-700/60 py-1">
                <span>{{ item.text }}</span>
                <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ item.tag }}</span>
              </li>
            </ul>
          </div>
        </section>

        <p class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-3 text-xs text-gray-500 dark:text-gray-400">
          A plain-language summary of the City's document, read {{ formatDate(plan.read_on) }}. Page numbers link to the original; where this sheet and the document differ, the document governs.
        </p>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { sectionHeadingClass } from '../Partials/classes'
import { formatDate } from '../Partials/format'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type PlanSection = {
  heading: string
  pages: number[]
  text?: string
  items?: string[]
  groups?: { label: string; items: { text: string; tag: string }[] }[]
}

defineProps<{
  plan: {
    slug: string
    title: string
    status: string
    source_url: string
    source_label: string
    page_url: string
    read_on: string
    summary: string[]
    sections: PlanSection[]
  }
}>()

const print = () => window.print()
</script>
