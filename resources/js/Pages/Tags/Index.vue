<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Browse by subject" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6" />
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Browse by subject</h1>
      <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
        <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-400">Every subject the election research files things under, and who campaigns on what. Open a subject to see where each candidate stands, what residents are raising and the coverage.</p>
        <div v-if="headings.length" class="inline-flex shrink-0 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-700" role="group" aria-label="View">
          <button
            v-for="v in (['matrix', 'list'] as const)"
            :key="v"
            type="button"
            :aria-pressed="view.mode === v"
            :class="view.mode === v ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'"
            class="tap-target-touch rounded-md px-3 py-1 text-sm font-medium transition"
            @click="view.mode = v"
          >{{ v === 'matrix' ? 'Who campaigns on what' : 'All subjects' }}</button>
        </div>
      </div>

      <p v-if="!headings.length" class="mt-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
        No subjects yet. The tag vocabulary arrives with a research import (Elections → Import XML).
      </p>

      <SubjectMatrix v-else-if="view.mode === 'matrix'" :candidates="candidates" :coverage="coverage" class="mt-6" />

      <template v-else>
        <section v-if="mostCovered.length" class="mt-8">
          <h2 :class="sectionHeadingClass">What the most candidates are campaigning on</h2>
          <ol class="mt-3 grid gap-2 sm:grid-cols-2">
            <li v-for="tag in mostCovered" :key="tag.slug">
              <Link :href="route('admin.elections.tags.show', tag.slug)" class="flex items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700">
                <span class="font-medium text-gray-900 dark:text-white">{{ tag.name }}</span>
                <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ tag.candidates }} candidate{{ tag.candidates === 1 ? '' : 's' }}</span>
              </Link>
            </li>
          </ol>
        </section>

        <section v-for="heading in headings" :key="heading.topic" class="mt-8">
          <h2 :class="sectionHeadingClass">{{ heading.title }}</h2>
          <ul class="mt-3 divide-y divide-gray-200 rounded-lg bg-white shadow-sm dark:divide-gray-700 dark:bg-gray-800">
            <li v-for="tag in heading.tags" :key="tag.slug">
              <Link :href="route('admin.elections.tags.show', tag.slug)" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700">
                <div class="flex items-baseline justify-between gap-3">
                  <span class="font-medium text-gray-900 dark:text-white">{{ tag.name }}</span>
                  <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="tag.candidates">{{ tag.candidates }} candidate{{ tag.candidates === 1 ? '' : 's' }} · </template>{{ tag.records }} item{{ tag.records === 1 ? '' : 's' }}
                  </span>
                </div>
                <p v-if="tag.description" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ tag.description }}</p>
              </Link>
            </li>
          </ul>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { Link, useRemember } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import SubjectMatrix from '../Partials/SubjectMatrix.vue'
import { sectionHeadingClass } from '../Partials/classes'
import type { SubjectCoverage } from '../Partials/coverage'
import type { Candidate, Options } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface TagRow {
  slug: string
  name: string
  description: string | null
  /** Candidates with a plank under this tag. */
  candidates: number
  /** Planks, statements, articles, pulse issues and scorecard statements carrying it. */
  records: number
}

defineProps<{
  headings: { topic: string; title: string; tags: TagRow[] }[]
  mostCovered: TagRow[]
  coverage: SubjectCoverage
  candidates: Candidate[]
  options: Options
}>()

// useRemember hands back a reactive object for object state.
const view = useRemember<{ mode: 'matrix' | 'list' }>({ mode: 'matrix' }, 'elections-subjects-view') as { mode: 'matrix' | 'list' }
</script>
