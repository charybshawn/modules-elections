<template>
  <div class="divide-y divide-gray-200 dark:divide-gray-700">
    <!-- Overview: whose questionnaire, the overall split, and what's ours vs theirs. -->
    <section class="py-6">
      <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ scorecard.title }}</h2>
        <a v-if="link" :href="link" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
          {{ scorecard.source_url ? 'Their answers on' : 'Open' }} {{ hostOf(link) }} &rarr;
        </a>
      </div>
      <p v-if="scorecard.publisher || scorecard.retrieved_on" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
        {{ [scorecard.publisher ? `By ${scorecard.publisher}` : null, scorecard.retrieved_on ? `as of ${formatDate(scorecard.retrieved_on)}` : null].filter(Boolean).join(' · ') }}
      </p>
      <p v-if="scorecard.about" class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ scorecard.about }}</p>

      <p v-if="!scorecard.responded" class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
        {{ candidateName }} did not answer this questionnaire{{ scorecard.retrieved_on ? ` as of ${formatDate(scorecard.retrieved_on)}` : '' }}.
        Not answering isn't a position either way; the statements they were asked are listed below.
      </p>
      <template v-else>
        <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img" :aria-label="totalsLabel">
          <div v-for="s in stanceOrder" :key="s" :class="stanceStyle[s].bar" :style="{ width: `${pct(scorecard.totals[s] ?? 0)}%` }" />
        </div>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ totalsLabel }}</p>
      </template>

      <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <li v-for="s in stanceOrder" :key="s" class="inline-flex items-center gap-1.5">
          <span :class="stanceStyle[s].chip" class="inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold">{{ stanceStyle[s].icon }}</span>{{ stances[s] }}
        </li>
      </ul>
      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Category headers, introductions and statements are {{ scorecard.publisher ?? scorecard.title }}'s own. "What this could mean for Salmon Arm" is an AI-assisted reading of the candidate's answers against local context; it isn't the candidate's words and doesn't judge whether a position is right.
      </p>
    </section>

    <AccountSection v-for="category in scorecard.categories" :key="category.key" :title="category.name" :description="category.intro ?? undefined">
      <div class="space-y-5">
        <p v-if="scorecard.responded" class="text-sm text-gray-600 dark:text-gray-300">{{ categoryLabel(category) }}</p>

        <ul class="space-y-3">
          <li v-for="item in category.items" :key="item.key" class="flex gap-3">
            <span
              :class="stanceStyle[item.stance]?.chip ?? stanceStyle.no_response.chip"
              class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-bold"
              :title="stances[item.stance] ?? item.stance"
            >{{ stanceStyle[item.stance]?.icon ?? '–' }}</span>
            <div class="min-w-0">
              <p class="text-sm sm:text-base text-gray-900 dark:text-gray-100">{{ item.statement }}</p>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium" :class="stanceStyle[item.stance]?.text">{{ stances[item.stance] ?? item.stance }}</span>
                <template v-if="fieldLabel(item)"> · {{ fieldLabel(item) }}</template>
              </p>
              <!-- Where they sit in the field: one dot per respondent, this candidate ringed. -->
              <div v-if="scorecard.responded && item.others.length" class="mt-1.5">
                <div class="flex flex-wrap items-center gap-1" role="img" :aria-label="fieldStripLabel(item)">
                  <span :class="stanceStyle[item.stance]?.bar" class="h-3 w-3 rounded-full ring-2 ring-gray-900 ring-offset-1 dark:ring-white dark:ring-offset-gray-800" :title="`${candidateName}: ${stances[item.stance] ?? item.stance}`" />
                  <span class="mx-0.5 h-3 w-px bg-gray-300 dark:bg-gray-600" />
                  <span v-for="o in sortedOthers(item)" :key="o.slug" :class="stanceStyle[o.stance]?.bar" class="h-2.5 w-2.5 rounded-full" :title="`${o.name}: ${stances[o.stance] ?? o.stance}`" />
                </div>
                <details class="group mt-1">
                  <summary class="tap-target-touch inline-flex cursor-pointer items-center text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Who answered what</summary>
                  <dl class="mt-1 space-y-0.5 text-xs">
                    <div v-for="group in othersByStance(item)" :key="group.stance" class="flex gap-2">
                      <dt :class="stanceStyle[group.stance]?.text" class="w-20 shrink-0 font-medium">{{ stances[group.stance] ?? group.stance }}</dt>
                      <dd class="text-gray-700 dark:text-gray-300">{{ group.names.join(', ') }}</dd>
                    </div>
                  </dl>
                </details>
              </div>
              <TagChips :tags="item.tags" class="mt-1" />
            </div>
          </li>
        </ul>

        <div v-if="category.takeaway" class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10">
          <h4 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">What this could mean for Salmon Arm</h4>
          <p class="mt-1 text-sm leading-relaxed text-indigo-950 dark:text-indigo-100 whitespace-pre-line">{{ category.takeaway }}</p>
        </div>

        <details v-if="category.local_context" class="text-sm text-gray-600 dark:text-gray-300">
          <summary class="tap-target-touch cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ category.name }} in Salmon Arm today</summary>
          <p class="mt-2 leading-relaxed whitespace-pre-line">{{ category.local_context }}</p>
          <p v-if="category.sources.length" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Sources:
            <template v-for="(url, i) in category.sources" :key="url">
              <a :href="url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 dark:decoration-gray-600">{{ hostOf(url) }}</a><template v-if="i < category.sources.length - 1">, </template>
            </template>
          </p>
        </details>
      </div>
    </AccountSection>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AccountSection from '@/Components/Admin/Accounts/AccountSection.vue'
import TagChips from './TagChips.vue'
import { formatDate, hostOf, isHttpUrl } from './format'
import type { Scorecard, ScorecardCategory, ScorecardItem } from './types'

const props = defineProps<{
  scorecard: Scorecard
  candidateName: string
  /** ScorecardAnswer::STANCES labels. */
  stances: Record<string, string>
}>()

const stanceOrder = ['supportive', 'neutral', 'opposed', 'no_response'] as const

const stanceStyle: Record<string, { icon: string; chip: string; bar: string; text: string }> = {
  supportive: { icon: '✓', chip: 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200', bar: 'bg-green-500', text: 'text-green-700 dark:text-green-400' },
  neutral: { icon: '~', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200', bar: 'bg-amber-400', text: 'text-amber-700 dark:text-amber-400' },
  opposed: { icon: '×', chip: 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200', bar: 'bg-red-500', text: 'text-red-700 dark:text-red-400' },
  no_response: { icon: '–', chip: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400', bar: 'bg-gray-300 dark:bg-gray-600', text: 'text-gray-500 dark:text-gray-400' },
}

/** The candidate's own page on the publisher's site, else the scorecard's. */
const link = computed(() => [props.scorecard.source_url, props.scorecard.url].find((u): u is string => isHttpUrl(u)) ?? null)

const itemCount = computed(() => props.scorecard.categories.reduce((n, c) => n + c.items.length, 0))
const pct = (n: number) => (itemCount.value ? (n / itemCount.value) * 100 : 0)

const splitLabel = (counts: Record<string, number>, total: number) =>
  [
    `Supportive on ${counts.supportive ?? 0} of ${total}`,
    counts.neutral ? `${counts.neutral} neutral` : null,
    counts.opposed ? `${counts.opposed} opposed` : null,
    counts.no_response ? `${counts.no_response} unanswered` : null,
  ]
    .filter(Boolean)
    .join(' · ')

const totalsLabel = computed(() => splitLabel(props.scorecard.totals, itemCount.value))

const categoryLabel = (category: ScorecardCategory) => {
  const counts: Record<string, number> = {}
  for (const item of category.items) counts[item.stance] = (counts[item.stance] ?? 0) + 1
  return splitLabel(counts, category.items.length)
}

const stanceRank: Record<string, number> = { supportive: 0, neutral: 1, opposed: 2, no_response: 3 }
const sortedOthers = (item: ScorecardItem) =>
  [...item.others].sort((a, b) => (stanceRank[a.stance] ?? 9) - (stanceRank[b.stance] ?? 9) || a.name.localeCompare(b.name))
const othersByStance = (item: ScorecardItem) =>
  stanceOrder
    .map((stance) => ({ stance, names: item.others.filter((o) => o.stance === stance).map((o) => o.name) }))
    .filter((g) => g.names.length)
const fieldStripLabel = (item: ScorecardItem) =>
  `${props.candidateName}: ${props.stances[item.stance] ?? item.stance}. Others: ` +
  othersByStance(item).map((g) => `${g.names.length} ${props.stances[g.stance]?.toLowerCase() ?? g.stance}`).join(', ')

/** How everyone who answered split on this statement, e.g. "Field: 9 supportive, 2 neutral, 1 opposed of 12". */
const fieldLabel = (item: ScorecardItem) => {
  const parts = (['supportive', 'neutral', 'opposed'] as const).filter((s) => item.field[s]).map((s) => `${item.field[s]} ${props.stances[s]?.toLowerCase() ?? s}`)
  return parts.length ? `All ${props.scorecard.respondents} respondents: ${parts.join(', ')}` : ''
}
</script>
