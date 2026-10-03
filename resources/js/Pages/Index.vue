<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Elections" />

    <div class="px-4 sm:px-0">
      <div class="md:flex md:items-start md:justify-between gap-6 mb-6">
        <div>
          <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Salmon Arm Election 2026</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Mayor and six councillors. Every item on a candidate's page links to where it came from.
          </p>
          <div class="mt-2 flex flex-wrap gap-x-5">
            <Link :href="route('admin.elections.tags.index')" class="inline-flex tap-target-touch items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">Browse by subject &rarr;</Link>
            <Link :href="route('admin.elections.pulse.index')" class="inline-flex tap-target-touch items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">Community Pulse: what residents are raising &rarr;</Link>
          </div>
          <p v-if="votingDay" class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">
            General voting day {{ formatDateTime(votingDay.starts_at) }}<template v-if="votingDayIn > 0"> · {{ votingDayIn }} day{{ votingDayIn === 1 ? '' : 's' }} away</template><template v-else-if="votingDayIn === 0"> · today</template>
          </p>
        </div>

        <div v-if="!readOnly" class="mt-4 md:mt-0 flex flex-wrap gap-2 shrink-0">
          <input ref="fileInput" type="file" accept=".xml,text/xml,application/xml" class="hidden" @change="handleFileChange" />
          <button
            type="button"
            :disabled="importForm.processing"
            title="Imports a research XML file. Re-importing updates what's already on file instead of duplicating it."
            :class="secondaryButtonClass"
            @click="fileInput?.click()"
          >{{ importForm.processing ? 'Importing...' : 'Import XML' }}</button>
          <!-- Plain <a>, not <Link>: Inertia would intercept the file download. -->
          <a :href="route('admin.elections.export')" :class="secondaryButtonClass">Export XML</a>
        </div>
      </div>

      <FormErrorSummary v-if="Object.keys(importForm.errors).length" :errors="importForm.errors" class="mb-6" />

      <dl class="grid grid-cols-3 gap-3 mb-8">
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Running</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.candidates }}</dd>
        </div>
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sourced items</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.entries }}</dd>
        </div>
        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Articles</dt>
          <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.articles }}</dd>
        </div>
      </dl>

      <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-8">
          <p v-if="newCandidateCount" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-amber-800 dark:text-amber-300">
            New information on {{ newCandidateCount }} candidate{{ newCandidateCount === 1 ? '' : 's' }} since your last visit.
            <button type="button" class="tap-target-touch text-xs font-medium underline underline-offset-2" @click="seen.markAllSeen()">Mark all as seen</button>
          </p>

          <section v-for="group in candidateGroups" :key="group.office">
            <h2 :class="sectionHeadingClass">{{ group.label }}</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
              <li v-for="candidate in group.candidates" :key="candidate.id">
                <Link
                  :href="route('admin.elections.candidates.show', candidate.slug)"
                  class="tap-target-touch flex items-center gap-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4 hover:ring-2 hover:ring-amber-400/60"
                  :class="candidate.status === 'withdrawn' ? 'opacity-60' : ''"
                >
                  <CandidatePhoto :name="candidate.name" :url="candidate.photo_url" class="h-14 w-14 shrink-0 rounded-full text-lg" />
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-gray-900 dark:text-white truncate">{{ candidate.name }}</span>
                      <span
                        v-if="newFor(candidate)"
                        class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                      >{{ newFor(candidate) }}</span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate">
                      <span v-if="candidate.is_incumbent" class="font-medium text-amber-700 dark:text-amber-400">Incumbent</span>
                      <span v-if="candidate.is_incumbent && candidate.occupation"> · </span>
                      <span>{{ candidate.occupation }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                      <span v-if="candidate.status !== 'nominated'">{{ options.statuses[candidate.status] ?? candidate.status }} · </span>
                      {{ candidate.entries_count ?? 0 }} item{{ candidate.entries_count === 1 ? '' : 's' }} · {{ candidate.articles_count ?? 0 }} article{{ candidate.articles_count === 1 ? '' : 's' }}
                    </div>
                  </div>
                </Link>
              </li>
            </ul>
          </section>

          <p v-if="candidates.length === 0" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
            No candidates on file yet.<template v-if="!readOnly"> Import a research XML file to get started.</template>
          </p>

          <section v-if="recentArticles.length">
            <h2 :class="sectionHeadingClass">Latest coverage</h2>
            <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700 rounded-lg bg-white dark:bg-gray-800 shadow-sm">
              <li v-for="article in recentArticles" :key="article.id" class="p-4">
                <a
                  v-if="isHttpUrl(article.url)"
                  :href="article.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-gray-900 hover:underline dark:text-white"
                >{{ article.title }}</a>
                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                  {{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}
                </div>
                <div v-if="article.candidates?.length" class="mt-1 flex flex-wrap gap-1.5">
                  <Link
                    v-for="c in article.candidates"
                    :key="c.slug"
                    :href="route('admin.elections.candidates.show', c.slug)"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                  >{{ c.name }}</Link>
                </div>
              </li>
            </ul>
          </section>
        </div>

        <aside class="space-y-6">
          <section>
            <h2 :class="sectionHeadingClass">Upcoming</h2>
            <p v-if="upcomingEvents.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No upcoming events on file.</p>
            <ol v-else class="mt-3 space-y-3">
              <li v-for="event in upcomingEvents" :key="event.id" class="rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4">
                <EventItem :event="event" :options="options" :read-only="readOnly" @delete="deleteEvent" />
              </li>
            </ol>
          </section>

          <section v-if="pastEvents.length">
            <details>
              <summary class="tap-target-touch cursor-pointer" :class="sectionHeadingClass">Past ({{ pastEvents.length }})</summary>
              <ol class="mt-3 space-y-3">
                <li v-for="event in pastEvents" :key="event.id" class="rounded-lg bg-white/60 dark:bg-gray-800/60 p-4">
                  <EventItem :event="event" :options="options" :read-only="readOnly" @delete="deleteEvent" />
                </li>
              </ol>
            </details>
          </section>
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import CandidatePhoto from './Partials/CandidatePhoto.vue'
import EventItem from './Partials/EventItem.vue'
import { secondaryButtonClass, sectionHeadingClass } from './Partials/classes'
import { daysUntil, formatDate, formatDateTime, isHttpUrl, useReadOnly } from './Partials/format'
import { toUnix, useSeen } from './Partials/seen'
import type { Article, Candidate, ElectionEvent, Options } from './Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  candidates: Candidate[]
  upcomingEvents: ElectionEvent[]
  pastEvents: ElectionEvent[]
  recentArticles: Article[]
  stats: { candidates: number; entries: number; articles: number }
  /** Unix times each candidate's entries were added and articles linked, keyed by candidate id. */
  activity: Record<number, number[]>
  /** Server time (Unix seconds). */
  now: number
  options: Options
}>()

const readOnly = useReadOnly()
const { confirmDialog } = useConfirmDialog()

const candidateGroups = computed(() =>
  Object.entries(props.options.offices)
    .map(([office, label]) => ({
      office,
      label: office === 'councillor' ? 'Council' : label,
      candidates: props.candidates.filter((c) => c.office === office),
    }))
    .filter((group) => group.candidates.length > 0),
)

// ---- New since last visit (per-viewer cookie) ----
const seen = useSeen(() => props.now)

/** "3 new", "Updated" (profile fields only) or '' -- against when the viewer last opened them. */
const newFor = (candidate: Candidate): string => {
  const since = seen.seenAt(candidate.slug)
  if (since === null) return ''
  const count = (props.activity[candidate.id] ?? []).filter((t) => t > since).length
  if (count > 0) return `${count} new`
  return (toUnix(candidate.updated_at) ?? 0) > since ? 'Updated' : ''
}

const newCandidateCount = computed(() => props.candidates.filter((c) => newFor(c) !== '').length)

const votingDay = computed(() => props.upcomingEvents.find((e) => e.kind === 'general_voting') ?? null)
const votingDayIn = computed(() => (votingDay.value ? daysUntil(votingDay.value.starts_at) : 0))

// ---- Events ----
const deleteEvent = async (event: ElectionEvent) => {
  const confirmed = await confirmDialog({
    title: 'Delete event',
    message: `Delete "${event.title}"?`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.events.destroy', event.id), { preserveScroll: true })
}

// ---- XML import ----
// A plain useForm: a File can't be persisted, and a draft selection
// wouldn't survive a reload anyway.
const importForm = useForm<{ file: File | null }>({ file: null })
const fileInput = ref<HTMLInputElement | null>(null)

const handleFileChange = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  if (!file) return

  importForm.file = file
  importForm.post(route('admin.elections.import'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => importForm.reset(),
    // Cleared either way, so picking the same file again still fires 'change'.
    onFinish: () => {
      if (fileInput.value) fileInput.value.value = ''
    },
  })
}
</script>
