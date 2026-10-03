<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader :title="tag.name" :href="route('admin.elections.tags.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6" />
      <Link :href="route('admin.elections.tags.index')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">&larr; All subjects</Link>
      <p class="mt-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ tag.heading }}</p>
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">{{ tag.name }}</h1>
      <p v-if="tag.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ tag.description }}</p>

      <ul v-if="related.length" class="mt-3 flex flex-wrap items-center gap-1.5 text-xs" aria-label="Related subjects">
        <li class="text-gray-500 dark:text-gray-400">Often discussed with:</li>
        <li v-for="r in related" :key="r.slug">
          <Link :href="route('admin.elections.tags.show', r.slug)" class="inline-flex rounded-full border border-gray-200 px-2 py-0.5 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ r.name }}</Link>
        </li>
      </ul>

      <!-- Where each candidate stands: their own planks on it, in their own emphasis, and their scorecard answers. -->
      <section class="mt-8">
        <h2 :class="sectionHeadingClass">Where candidates stand</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">From each candidate's own platform (ranked by their own emphasis) and their answers to published scorecards. Order follows how prominently they put it, not a judgement of the position.</p>

        <p v-if="!positions.length" class="mt-3 rounded-lg bg-white p-4 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">No candidate has a plank or scorecard answer on this yet.</p>
        <ul v-else class="mt-3 space-y-3">
          <li v-for="p in positions" :key="p.slug" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
              <Link :href="route('admin.elections.candidates.show', { candidate: p.slug, tab: p.planks.length ? 'platform' : 'scorecards' })" class="font-medium text-gray-900 hover:underline dark:text-white">{{ p.name }}</Link>
              <span class="text-xs text-gray-500 dark:text-gray-400">{{ options.offices[p.office] ?? p.office }}</span>
            </div>
            <ul v-if="p.planks.length" class="mt-2 space-y-2">
              <li v-for="plank in p.planks" :key="plank.key" class="text-sm">
                <span :class="tierClass(plank.tier)" class="mr-1.5 rounded px-1.5 py-0.5 text-xs font-medium">{{ options.plankTiers[plank.tier] ?? plank.tier }}</span>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ plank.title }}</span>
                <p v-if="plank.summary" class="mt-0.5 text-gray-600 dark:text-gray-300">{{ plank.summary }}</p>
              </li>
            </ul>
            <ul v-if="Object.keys(p.stances).length" class="mt-2 space-y-1 text-sm">
              <li v-for="item in scorecardItems.filter((i) => p.stances[i.id])" :key="item.id" class="flex gap-2">
                <span :class="stanceClass(p.stances[item.id])" class="mt-0.5 inline-flex h-5 shrink-0 items-center rounded-full px-1.5 text-xs font-medium">{{ options.scorecardStances[p.stances[item.id]] ?? p.stances[item.id] }}</span>
                <span class="text-gray-700 dark:text-gray-300">{{ item.statement }} <span class="text-xs text-gray-500 dark:text-gray-400">({{ item.scorecard }})</span></span>
              </li>
            </ul>
          </li>
        </ul>

        <p v-if="nothingOnFile.length" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
          <span class="font-medium text-gray-700 dark:text-gray-300">Nothing on file on this yet:</span>
          <template v-for="(c, i) in nothingOnFile" :key="c.slug">
            <Link :href="route('admin.elections.candidates.show', c.slug)" class="hover:underline">{{ c.name }}</Link><template v-if="i < nothingOnFile.length - 1">, </template>
          </template>.
          That means the research hasn't found them addressing it, not that they have no view.
        </p>
      </section>

      <section v-if="scorecardItems.length" class="mt-10">
        <h2 :class="sectionHeadingClass">Scorecard statements</h2>
        <ul class="mt-3 space-y-2">
          <li v-for="item in scorecardItems" :key="item.id" class="rounded-lg bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
            <p class="text-gray-900 dark:text-gray-100">{{ item.statement }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ item.scorecard }} · {{ item.category }}<template v-if="fieldLabel(item.field)"> · {{ fieldLabel(item.field) }}</template>
            </p>
          </li>
        </ul>
      </section>

      <section v-if="pulseIssues.length" class="mt-10">
        <h2 :class="sectionHeadingClass">What residents are raising</h2>
        <ul class="mt-3 space-y-2">
          <li v-for="issue in pulseIssues" :key="`${issue.taken_on}-${issue.key}`" class="rounded-lg bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
            <Link :href="route('admin.elections.pulse.index', { date: issue.taken_on })" class="font-medium text-gray-900 hover:underline dark:text-white">{{ issue.title }}</Link>
            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ issue.voices }} people · {{ formatDate(issue.taken_on) }}</span>
            <p v-if="issue.summary" class="mt-1 text-gray-600 dark:text-gray-300">{{ issue.summary }}</p>
          </li>
        </ul>
      </section>

      <section v-if="articles.length" class="mt-10">
        <h2 :class="sectionHeadingClass">In the news</h2>
        <ul class="mt-3 space-y-3">
          <li v-for="article in articles" :key="article.id" class="text-sm">
            <a v-if="isHttpUrl(article.url)" :href="article.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 hover:underline dark:text-white">{{ article.title }}</a>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}</div>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { sectionHeadingClass } from '../Partials/classes'
import { formatDate, isHttpUrl } from '../Partials/format'
import type { Article, Options } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface Position {
  name: string
  slug: string
  office: string
  planks: { key: string; title: string; summary: string | null; tier: string; rank: number }[]
  /** Scorecard item id ("scorecard/item") -> stance. */
  stances: Record<string, string>
}

interface TaggedScorecardItem {
  id: string
  scorecard: string
  category: string
  statement: string
  field: Record<string, number>
}

defineProps<{
  tag: { slug: string; name: string; topic: string; heading: string; description: string | null }
  positions: Position[]
  nothingOnFile: { name: string; slug: string }[]
  scorecardItems: TaggedScorecardItem[]
  pulseIssues: { key: string; title: string; voices: number; heat: string; summary: string | null; taken_on: string }[]
  articles: Article[]
  related: { slug: string; name: string; together: number }[]
  options: Options
}>()

const tierClass = (tier: string) =>
  ({
    top: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
    also: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
  })[tier] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'

const stanceClass = (stance: string) =>
  ({
    supportive: 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200',
    neutral: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
    opposed: 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
  })[stance] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'

const fieldLabel = (field: Record<string, number>) =>
  (['supportive', 'neutral', 'opposed'] as const)
    .filter((s) => field[s])
    .map((s) => `${field[s]} ${s}`)
    .join(', ')
</script>
