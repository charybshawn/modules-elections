<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader :title="candidate.name" :href="route('admin.elections.index')" />

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 sm:rounded-lg sm:shadow-sm">
        <!-- Header -->
        <header class="p-4 sm:p-6">
          <Link :href="route('admin.elections.index')" class="hidden md:inline-flex tap-target-touch text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">&larr; All candidates</Link>

          <div class="mt-2 flex flex-col sm:flex-row gap-5">
            <CandidatePhoto :name="candidate.name" :url="candidate.photo_url" class="h-28 w-28 shrink-0 rounded-lg text-3xl" />
            <div class="min-w-0 flex-1">
              <div class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-400">
                For {{ options.offices[candidate.office] ?? candidate.office }}<template v-if="candidate.is_incumbent"> · Incumbent</template>
              </div>
              <h1 class="mt-1 text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white">{{ candidate.name }}</h1>
              <p v-if="candidate.occupation" class="mt-1 text-gray-600 dark:text-gray-300">{{ candidate.occupation }}</p>
              <p v-if="candidate.status !== 'nominated'" class="mt-1 text-sm" :class="candidate.status === 'withdrawn' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">
                {{ options.statuses[candidate.status] ?? candidate.status }}<template v-if="candidate.status === 'declared'"> -- not yet confirmed on the City's official nominations list</template>
              </p>

              <ul v-if="links.length" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <li v-for="link in links" :key="link.label">
                  <a :href="link.href" :target="link.external ? '_blank' : undefined" :rel="link.external ? 'noopener noreferrer' : undefined" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ link.label }}</a>
                </li>
              </ul>
            </div>

            <div v-if="!readOnly" class="flex sm:flex-col gap-2 shrink-0">
              <button type="button" :class="secondaryButtonClass" class="!text-red-600 dark:!text-red-400" @click="destroyCandidate">Delete</button>
            </div>
          </div>

          <p v-if="newSummary" class="mt-4 text-sm font-medium text-amber-800 dark:text-amber-300">{{ newSummary }}</p>

          <div v-if="!readOnly && candidate.notes" class="mt-4 rounded-md bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
            <div class="font-medium">Research notes <span class="font-normal text-amber-700 dark:text-amber-300/70">(admins only)</span></div>
            <p class="mt-1 whitespace-pre-line">{{ candidate.notes }}</p>
          </div>
        </header>

        <!-- Tabs: underline tabs from sm up, a section picker on phones -->
        <div class="border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6">
          <label for="candidate-section" class="sr-only">Section</label>
          <select
            id="candidate-section"
            v-model="activeTab"
            class="sm:hidden mb-4 block w-full rounded-md border-gray-300 text-base dark:border-gray-600 dark:bg-gray-700 dark:text-white"
          >
            <option v-for="tab in tabs" :key="tab.id" :value="tab.id">{{ tab.title }}{{ tab.count ? ` (${tab.count})` : '' }}{{ tab.hasNew ? ' · new' : '' }}</option>
          </select>

          <nav class="hidden sm:flex -mb-px gap-6 overflow-x-auto scrollbar-hide" role="tablist" aria-label="Candidate sections">
            <button
              v-for="tab in tabs"
              :id="`tab-${tab.id}`"
              :key="tab.id"
              type="button"
              role="tab"
              :aria-selected="activeTab === tab.id"
              :aria-controls="`panel-${tab.id}`"
              :class="[
                'tap-target-touch inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors',
                activeTab === tab.id
                  ? 'border-amber-500 text-gray-900 dark:text-white'
                  : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
              ]"
              @click="activeTab = tab.id"
            >
              {{ tab.title }}
              <span v-if="tab.count" class="text-xs font-normal text-gray-400 dark:text-gray-500">{{ tab.count }}</span>
              <span v-if="tab.hasNew" class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-label="has new items" />
            </button>
          </nav>
        </div>

        <!-- About -->
        <div v-show="activeTab === 'about'" id="panel-about" role="tabpanel" aria-labelledby="tab-about" class="p-4 sm:p-6 space-y-8">
          <section>
            <h2 :class="subsectionHeadingClass">Biography</h2>
            <template v-if="candidate.bio">
              <p class="mt-4 text-sm sm:text-base leading-relaxed text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ candidate.bio }}</p>
              <p v-if="isHttpUrl(candidate.bio_source_url)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Source: <a :href="candidate.bio_source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 dark:decoration-gray-600">{{ hostOf(candidate.bio_source_url) }}</a>
              </p>
            </template>
            <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">No biography on file yet.</p>
          </section>

          <section v-for="group in portfolio.background" :key="group.topic ?? 'all'">
            <h2 :class="subsectionHeadingClass">{{ options.backgroundTopics[group.topic ?? ''] ?? group.topic }}</h2>
            <FactList class="mt-4" :entries="group.entries" :editable="!readOnly" :is-new="isNewEntry" @delete="deleteEntry" />
          </section>

          <p v-if="tabs.length === 1" class="text-sm text-gray-500 dark:text-gray-400">
            Nothing on their platform or statements is on file yet -- the next research import will fill this in.
          </p>
        </div>

        <!-- Platform, In their own words, Prior record, Endorsements, Campaign finance -->
        <div
          v-for="section in portfolio.sections"
          v-show="activeTab === section.key"
          :id="`panel-${section.key}`"
          :key="section.key"
          role="tabpanel"
          :aria-labelledby="`tab-${section.key}`"
          class="p-4 sm:p-6 space-y-8"
        >
          <section v-for="group in section.groups" :key="group.topic ?? 'all'">
            <h2 :class="subsectionHeadingClass">{{ group.topic ? (options.topics[group.topic] ?? group.topic) : section.title }}</h2>
            <ul class="mt-4 space-y-5">
              <li v-for="entry in group.entries" :key="entry.id">
                <EntryCard
                  :entry="entry"
                  :options="options"
                  :editable="!readOnly"
                  :show-kind="section.key === 'words'"
                  :is-new="isNewEntry(entry)"
                  @delete="deleteEntry"
                />
              </li>
            </ul>
          </section>
        </div>

        <!-- In the news -->
        <div v-if="portfolio.articles.length" v-show="activeTab === 'news'" id="panel-news" role="tabpanel" aria-labelledby="tab-news" class="p-4 sm:p-6">
          <h2 :class="subsectionHeadingClass">Coverage</h2>
          <ul class="mt-4 space-y-5">
            <li v-for="article in portfolio.articles" :key="article.id">
              <a v-if="isHttpUrl(article.url)" :href="article.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 hover:underline dark:text-white">{{ article.title }}</a>
              <span v-if="isNewArticle(article)" :class="newPillClass" class="ml-2 align-middle">New</span>
              <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}</div>
              <p v-if="article.summary" class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ article.summary }}</p>
              <button v-if="!readOnly" type="button" class="tap-target-touch mt-1 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="deleteArticle(article)">Remove article</button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import CandidatePhoto from '../Partials/CandidatePhoto.vue'
import EntryCard from '../Partials/EntryCard.vue'
import FactList from '../Partials/FactList.vue'
import { newPillClass, secondaryButtonClass, subsectionHeadingClass } from '../Partials/classes'
import { formatDate, hostOf, isHttpUrl, useReadOnly } from '../Partials/format'
import { toUnix, useSeen } from '../Partials/seen'
import type { Article, Entry, Options, Portfolio } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  portfolio: Portfolio
  options: Options
}>()

// Computed: Inertia replaces the prop object on every visit.
const candidate = computed(() => props.portfolio.candidate)
const readOnly = useReadOnly()
const { confirmDialog } = useConfirmDialog()

const links = computed(() => {
  const c = candidate.value
  const list: { label: string; href: string; external: boolean }[] = []
  if (isHttpUrl(c.website)) list.push({ label: 'Website', href: c.website, external: true })
  if (isHttpUrl(c.facebook_url)) list.push({ label: 'Facebook', href: c.facebook_url, external: true })
  if (isHttpUrl(c.instagram_url)) list.push({ label: 'Instagram', href: c.instagram_url, external: true })
  if (c.email) list.push({ label: c.email, href: `mailto:${c.email}`, external: false })
  if (c.phone) list.push({ label: c.phone, href: `tel:${c.phone}`, external: false })
  return list
})

// ---- New since last visit (per-viewer cookie) ----
// Snapshot when the viewer last opened this candidate, then mark it seen:
// items newer than the snapshot stay tagged "New" for this visit. useSeen
// reads the cookie in its own onMounted, registered (so run) before this one.
const seen = useSeen(() => props.portfolio.now)
const lastVisit = ref<number | null>(null)
onMounted(() => {
  lastVisit.value = seen.seenAt(candidate.value.slug)
  seen.markSeen(candidate.value.slug)
})

const isNewer = (iso: string | null | undefined) => lastVisit.value !== null && (toUnix(iso) ?? 0) > lastVisit.value
const isNewEntry = (entry: Entry) => isNewer(entry.added_at)
const isNewArticle = (article: Article) => isNewer(article.linked_at)

const backgroundEntries = computed(() => props.portfolio.background.flatMap((g) => g.entries))
const sectionEntries = (key: string) => props.portfolio.sections.find((s) => s.key === key)?.groups.flatMap((g) => g.entries) ?? []

const newSummary = computed(() => {
  if (lastVisit.value === null) return ''
  const entries = [...backgroundEntries.value, ...props.portfolio.sections.flatMap((s) => s.groups.flatMap((g) => g.entries))]
  const count = entries.filter(isNewEntry).length + props.portfolio.articles.filter(isNewArticle).length
  if (count > 0) return `${count} new item${count === 1 ? '' : 's'} since your last visit -- look for the dot on a tab and the "New" tag on the item.`
  return isNewer(candidate.value.updated_at) ? 'Profile details updated since your last visit.' : ''
})

// ---- Tabs ----
// About always; then one tab per non-empty portfolio section, then news.
// The open tab lives in the URL hash so a reload or shared link keeps it.
const tabs = computed(() => [
  { id: 'about', title: 'About', count: backgroundEntries.value.length, hasNew: backgroundEntries.value.some(isNewEntry) },
  ...props.portfolio.sections.map((s) => {
    const entries = sectionEntries(s.key)
    return { id: s.key, title: s.title, count: entries.length, hasNew: entries.some(isNewEntry) }
  }),
  ...(props.portfolio.articles.length
    ? [{ id: 'news', title: 'In the news', count: props.portfolio.articles.length, hasNew: props.portfolio.articles.some(isNewArticle) }]
    : []),
])

const activeTab = ref('about')

onMounted(() => {
  const fromHash = location.hash.slice(1)
  if (tabs.value.some((t) => t.id === fromHash)) activeTab.value = fromHash
})

watch(activeTab, (id) => {
  // Keep Inertia's history state; only the fragment changes.
  history.replaceState(history.state, '', id === 'about' ? location.pathname + location.search : `#${id}`)
})

// A deleted last item can make the open tab disappear.
watch(tabs, (list) => {
  if (!list.some((t) => t.id === activeTab.value)) activeTab.value = 'about'
})

// ---- Entries ----
const deleteEntry = async (entry: Entry) => {
  const confirmed = await confirmDialog({
    title: 'Delete entry',
    message: `Delete "${entry.summary.slice(0, 80)}${entry.summary.length > 80 ? '…' : ''}"? A later import would bring it back if the research file still has it.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) {
    router.delete(route('admin.elections.entries.destroy', [candidate.value.slug, entry.id]), { preserveScroll: true, preserveState: true })
  }
}

const deleteArticle = async (article: Article) => {
  const confirmed = await confirmDialog({
    title: 'Remove article',
    message: `Remove "${article.title}" from every candidate it's linked to?`,
    confirmLabel: 'Remove',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.articles.destroy', article.id), { preserveScroll: true, preserveState: true })
}

const destroyCandidate = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete candidate',
    message: `Delete ${candidate.value.name} and all ${props.portfolio.entryCount} item(s) on file for them? This can't be undone.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.candidates.destroy', candidate.value.slug))
}
</script>
