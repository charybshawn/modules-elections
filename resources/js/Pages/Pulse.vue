<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Community Pulse" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6" />
      <div class="md:flex md:items-start md:justify-between gap-6 mb-6">
        <div>
          <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Community Pulse</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">What residents are raising in local election discussion, summarized, and how it lines up with the candidates' platforms.</p>
        </div>

        <div v-if="snapshots.length" class="mt-4 md:mt-0 flex items-center gap-2 shrink-0">
          <label for="pulse-date" class="text-sm text-gray-600 dark:text-gray-400">Snapshot</label>
          <select
            id="pulse-date"
            :value="snapshot?.taken_on"
            class="rounded-md border-gray-300 text-base sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            @change="showDate(($event.target as HTMLSelectElement).value)"
          >
            <option v-for="d in snapshots" :key="d" :value="d">{{ formatDate(d) }}</option>
          </select>
          <button v-if="!readOnly && snapshot" type="button" :class="secondaryButtonClass" class="!text-red-600 dark:!text-red-400" @click="deleteSnapshot">Delete</button>
        </div>
      </div>

      <p v-if="!snapshot" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
        No pulse snapshot yet. Snapshots are written by the community-pulse research pass and imported through Elections → Import XML.
      </p>

      <template v-else>
        <!-- How this was made: always first, so the conclusions are read in context. -->
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 mb-8">
          <h2 class="font-semibold">How this was made</h2>
          <p class="mt-1">
            Read {{ snapshot.threads_read }} discussion thread{{ snapshot.threads_read === 1 ? '' : 's' }} with {{ snapshot.commenters }} different people commenting<template v-if="snapshot.sources"> in {{ snapshot.sources }}</template><template v-if="snapshot.period_from && snapshot.period_to">, {{ formatDate(snapshot.period_from) }} to {{ formatDate(snapshot.period_to) }}</template>.
            Issues are weighted by how many different people raised them, not by how many comments. This is a summary of one online conversation, not a poll; no resident is named or quoted, and the summaries are AI-assisted.
          </p>
          <p v-if="snapshot.method_note" class="mt-2 whitespace-pre-line">{{ snapshot.method_note }}</p>
        </section>

        <section v-if="snapshot.conclusions.length" class="mb-10">
          <h2 :class="sectionHeadingClass">What stands out</h2>
          <ol class="mt-3 space-y-3 list-decimal pl-5 marker:font-semibold marker:text-amber-700 dark:marker:text-amber-400">
            <li v-for="(c, i) in snapshot.conclusions" :key="i" class="text-base leading-relaxed text-gray-900 dark:text-gray-100">
              {{ c.text }}
              <a v-if="c.issue && issueKeys.has(c.issue)" :href="`#issue-${c.issue}`" class="ml-1 text-sm text-indigo-600 hover:underline dark:text-indigo-400">See the issue &darr;</a>
            </li>
          </ol>
        </section>

        <section class="mb-10">
          <h2 :class="sectionHeadingClass">Issues residents are raising</h2>
          <p v-if="snapshot.previous_taken_on" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Changes are compared with the {{ formatDate(snapshot.previous_taken_on) }} snapshot.</p>
          <ul class="mt-4 space-y-4">
            <li v-for="issue in snapshot.issues" :id="`issue-${issue.key}`" :key="issue.key" class="scroll-mt-24 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4 sm:p-6">
              <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ issue.title }}</h3>
                <div class="flex items-center gap-2 text-sm">
                  <span class="font-semibold text-gray-900 dark:text-white">{{ issue.voices }} {{ issue.voices === 1 ? 'person' : 'people' }}</span>
                  <span v-if="issue.voices_change !== null" :class="changeClass(issue.voices_change)" class="text-xs">{{ changeLabel(issue.voices_change) }}</span>
                  <span :class="heatClass(issue.heat)" class="rounded-full px-2 py-0.5 text-xs font-medium">{{ heat[issue.heat] ?? issue.heat }} heat</span>
                </div>
              </div>
              <TagChips v-if="issue.tags.length" :tags="issue.tags" class="mt-2" />
              <div v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ options.topics[issue.topic] ?? issue.topic }}</div>

              <!-- Stance split -->
              <div v-if="hasStance(issue)" class="mt-3">
                <div class="flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img" :aria-label="stanceLabel(issue)">
                  <div class="bg-green-500" :style="{ width: `${issue.support_pct ?? 0}%` }" />
                  <div class="bg-gray-400" :style="{ width: `${issue.mixed_pct ?? 0}%` }" />
                  <div class="bg-red-500" :style="{ width: `${issue.oppose_pct ?? 0}%` }" />
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ stanceLabel(issue) }}</p>
              </div>

              <p v-if="issue.summary" class="mt-3 text-sm sm:text-base leading-relaxed text-gray-800 dark:text-gray-200">{{ issue.summary }}</p>

              <div class="mt-4 grid gap-6 md:grid-cols-2">
                <div v-if="issue.wants.length">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white">What residents want</h4>
                  <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-300 list-disc pl-5">
                    <li v-for="(w, i) in issue.wants" :key="i">{{ w }}</li>
                  </ul>
                </div>
                <div v-if="issue.questions.length">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Questions residents keep asking</h4>
                  <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-300 list-disc pl-5">
                    <li v-for="(q, i) in issue.questions" :key="i">{{ q }}</li>
                  </ul>
                </div>
              </div>

              <!-- Residents vs candidates -->
              <div v-if="issue.coverage" class="mt-5 border-t border-gray-200 dark:border-gray-700 pt-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                  Candidates campaigning on {{ issue.coverage_by === 'tags' ? issue.tags.map((t) => t.name.toLowerCase()).join(' or ') : (options.topics[issue.topic] ?? issue.topic).toLowerCase() }}
                </h4>
                <p v-if="coverageCount(issue) === 0" class="mt-1 text-sm text-amber-800 dark:text-amber-300">No candidate has a plank on this yet.</p>
                <dl v-else class="mt-2 space-y-2 text-sm">
                  <div v-for="(people, tier) in issue.coverage" :key="tier" v-show="people.length" class="flex flex-col sm:flex-row sm:gap-3">
                    <dt class="shrink-0 sm:w-44 text-gray-500 dark:text-gray-400">{{ options.plankTiers[tier] ?? tier }} ({{ people.length }})</dt>
                    <dd class="flex flex-wrap gap-1.5">
                      <Link
                        v-for="p in people"
                        :key="p.slug"
                        :href="route('admin.elections.candidates.show', { candidate: p.slug, tab: 'platform' })"
                        :title="p.plank"
                        class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                      >{{ p.name }}</Link>
                    </dd>
                  </div>
                </dl>
              </div>
            </li>
          </ul>
        </section>

        <section v-if="snapshot.mentions.length" class="mb-6">
          <h2 :class="sectionHeadingClass">Candidates mentioned</h2>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How often each candidate came up by name in the threads read. Counts only, with no judgement of what was said.</p>
          <div class="mt-3 rounded-lg bg-white dark:bg-gray-800 shadow-sm divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="m in snapshot.mentions" :key="m.slug" class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
              <Link :href="route('admin.elections.candidates.show', m.slug)" class="font-medium text-gray-900 hover:underline dark:text-white">{{ m.name }}</Link>
              <span class="text-gray-600 dark:text-gray-300">
                {{ m.mentions }} mention{{ m.mentions === 1 ? '' : 's' }} by {{ m.commenters }} {{ m.commenters === 1 ? 'person' : 'people' }}
                <span v-if="m.mentions_change !== null" :class="changeClass(m.mentions_change)" class="ml-1 text-xs">{{ changeLabel(m.mentions_change) }}</span>
              </span>
            </div>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import ElectionsNav from './Partials/ElectionsNav.vue'
import { computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import TagChips from './Partials/TagChips.vue'
import { secondaryButtonClass, sectionHeadingClass } from './Partials/classes'
import { formatDate, useReadOnly } from './Partials/format'
import { useUpdates } from './Partials/updates'
import type { Options, PulseIssue, PulseSnapshot } from './Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  snapshot: PulseSnapshot | null
  /** Every snapshot date, newest first. */
  snapshots: string[]
  heat: Record<string, string>
  options: Options
}>()

const readOnly = useReadOnly()

// Opening Community Pulse marks it seen (the nav tab stops being orange).
const updates = useUpdates()
onMounted(async () => {
  await updates.ensure()
  updates.mark('pulse')
})
const { confirmDialog } = useConfirmDialog()

const issueKeys = computed(() => new Set(props.snapshot?.issues.map((i) => i.key) ?? []))

const showDate = (date: string) => router.get(route('admin.elections.pulse.index'), { date }, { preserveScroll: true })

const hasStance = (i: PulseIssue) => [i.support_pct, i.oppose_pct, i.mixed_pct].some((v) => v !== null && v > 0)
const stanceLabel = (i: PulseIssue) =>
  [i.support_pct !== null ? `${i.support_pct}% supportive` : null, i.mixed_pct !== null ? `${i.mixed_pct}% mixed` : null, i.oppose_pct !== null ? `${i.oppose_pct}% opposed` : null]
    .filter(Boolean)
    .join(' · ')

const heatClass = (heat: string) =>
  ({
    high: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
    medium: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
    low: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  })[heat] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'

const changeLabel = (c: number | 'new') => (c === 'new' ? 'new' : c > 0 ? `+${c}` : c === 0 ? 'no change' : `${c}`)
const changeClass = (c: number | 'new') =>
  c === 'new' || (typeof c === 'number' && c > 0) ? 'text-green-700 dark:text-green-400' : typeof c === 'number' && c < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'

const coverageCount = (i: PulseIssue) => Object.values(i.coverage ?? {}).reduce((n, people) => n + people.length, 0)

const deleteSnapshot = async () => {
  if (!props.snapshot) return
  const confirmed = await confirmDialog({
    title: 'Delete snapshot',
    message: `Delete the ${formatDate(props.snapshot.taken_on)} pulse snapshot? Re-importing its file would bring it back.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.pulse.destroy', props.snapshot.id))
}
</script>
