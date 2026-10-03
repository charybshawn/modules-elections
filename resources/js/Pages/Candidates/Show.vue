<template>
  <AdminShowShell>
    <template #mobile-header>
      <AdminMobileHeader :title="candidate.name" :href="route('admin.elections.index')" />
    </template>

    <template v-if="!readOnly" #actions>
      <IconButton
        label="Delete candidate"
        class="rounded-md text-gray-400 hover:text-red-600 active:bg-red-50 dark:text-gray-500 dark:hover:text-red-400 dark:active:bg-red-900/20"
        @click="destroyCandidate"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </IconButton>
    </template>

    <template #header>
      <Link :href="route('admin.elections.index')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">
        &larr; Back to Elections
      </Link>
      <!-- pr-40 keeps a long name clear of the sticky actions pill. -->
      <h1 class="hidden md:block mt-2 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">{{ candidate.name }}</h1>
    </template>

    <div class="space-y-8 md:mt-6">
      <!-- Identity and key facts (both breakpoints): photo above the facts on phones. -->
      <div class="flex flex-col sm:flex-row gap-5">
        <CandidatePhoto :name="candidate.name" :url="candidate.photo_url" class="h-28 w-28 shrink-0 rounded-lg text-3xl" />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
              {{ options.offices[candidate.office] ?? candidate.office }}
            </span>
            <span v-if="candidate.is_incumbent" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Incumbent</span>
            <span :class="statusBadgeClass" class="px-2 py-0.5 text-xs font-semibold rounded-full">{{ options.statuses[candidate.status] ?? candidate.status }}</span>
          </div>

          <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-if="candidate.occupation" class="min-w-0 sm:col-span-2">
              <dt class="text-sm text-gray-500 dark:text-gray-400">Occupation</dt>
              <dd class="text-gray-900 dark:text-white">{{ candidate.occupation }}</dd>
            </div>
            <div v-for="fact in contactFacts" :key="fact.label" class="min-w-0">
              <dt class="text-sm text-gray-500 dark:text-gray-400">{{ fact.label }}</dt>
              <dd class="text-gray-900 dark:text-white break-words">
                <a :href="fact.href" :target="fact.external ? '_blank' : undefined" :rel="fact.external ? 'noopener noreferrer' : undefined" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ fact.text }}</a>
              </dd>
            </div>
          </dl>
          <p v-if="candidate.status === 'declared'" class="mt-3 text-sm text-gray-500 dark:text-gray-400">Not yet confirmed on the City's official nominations list.</p>
        </div>
      </div>

      <p v-if="newSummary" class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ newSummary }}</p>

      <!-- Tabs: each is its own URL (?tab=), like Customers' views. Underline
           tabs from md up; a native picker on phones, where seven tabs
           wouldn't fit across. -->
      <div>
        <label for="candidate-tab" class="sr-only">Section</label>
        <select
          id="candidate-tab"
          :value="activeTab"
          class="md:hidden block w-full rounded-md border-gray-300 text-base dark:border-gray-600 dark:bg-gray-700 dark:text-white"
          @change="switchTab(($event.target as HTMLSelectElement).value)"
        >
          <option v-for="tab in tabs" :key="tab.id" :value="tab.id">{{ tab.title }}{{ tab.count ? ` (${tab.count})` : '' }}{{ tab.hasNew ? ' · new' : '' }}</option>
        </select>

        <nav class="hidden md:flex gap-6 overflow-x-auto scrollbar-hide border-b border-gray-200 dark:border-gray-700" aria-label="Candidate sections">
          <Link
            v-for="tab in tabs"
            :key="tab.id"
            :href="tabHref(tab.id)"
            preserve-state
            preserve-scroll
            :aria-current="activeTab === tab.id ? 'page' : undefined"
            :class="[
              'tap-target-touch -mb-px inline-flex items-center gap-1.5 py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors',
              activeTab === tab.id
                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600',
            ]"
          >
            {{ tab.title }}
            <span v-if="tab.count" class="text-xs font-normal text-gray-400 dark:text-gray-500">{{ tab.count }}</span>
            <span v-if="tab.hasNew" class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-label="has new items" />
          </Link>
        </nav>

        <!-- About: the City councillor-page layout -- a biography, then each
             background category as a short bulleted list. -->
        <div v-if="activeTab === 'about'" class="divide-y divide-gray-200 dark:divide-gray-700">
          <AccountSection title="Biography" description="A short, neutral summary, with where it came from.">
            <template v-if="candidate.bio">
              <p class="text-sm sm:text-base leading-relaxed text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ candidate.bio }}</p>
              <p v-if="isHttpUrl(candidate.bio_source_url)" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Source: <a :href="candidate.bio_source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 dark:decoration-gray-600">{{ hostOf(candidate.bio_source_url) }}</a>
              </p>
            </template>
            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No biography on file yet.</p>
          </AccountSection>

          <AccountSection
            v-for="group in portfolio.background"
            :key="group.topic ?? 'all'"
            :title="options.backgroundTopics[group.topic ?? ''] ?? group.topic ?? ''"
            :description="backgroundDescriptions[group.topic ?? '']"
          >
            <FactList :entries="group.entries" :editable="!readOnly" :is-new="isNewEntry" @delete="deleteEntry" />
          </AccountSection>

          <p v-if="tabs.length === 1" class="py-6 text-sm text-gray-500 dark:text-gray-400">
            Nothing on their platform or statements is on file yet -- the next research import will fill this in.
          </p>
        </div>

        <!-- Platform, In their own words, Prior record, Endorsements, Campaign finance -->
        <div v-else-if="activeSection" class="divide-y divide-gray-200 dark:divide-gray-700">
          <AccountSection
            v-for="group in activeSection.groups"
            :key="group.topic ?? 'all'"
            :title="group.topic ? (options.topics[group.topic] ?? group.topic) : activeSection.title"
          >
            <ul class="space-y-5">
              <li v-for="entry in group.entries" :key="entry.id">
                <EntryCard
                  :entry="entry"
                  :options="options"
                  :editable="!readOnly"
                  :show-kind="activeSection.key === 'words'"
                  :is-new="isNewEntry(entry)"
                  @delete="deleteEntry"
                />
              </li>
            </ul>
          </AccountSection>
        </div>

        <!-- In the news -->
        <div v-else-if="activeTab === 'news'">
          <AccountSection title="Coverage" description="News stories that mention this candidate.">
            <ul class="space-y-5">
              <li v-for="article in portfolio.articles" :key="article.id">
                <a v-if="isHttpUrl(article.url)" :href="article.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 hover:underline dark:text-white">{{ article.title }}</a>
                <span v-if="isNewArticle(article)" :class="newPillClass" class="ml-2 align-middle">New</span>
                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}</div>
                <p v-if="article.summary" class="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ article.summary }}</p>
                <button v-if="!readOnly" type="button" class="tap-target-touch mt-1 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="deleteArticle(article)">Remove article</button>
              </li>
            </ul>
          </AccountSection>
        </div>

        <!-- Research notes: admins only (the server omits notes for invited viewers) -->
        <div v-else-if="activeTab === 'notes'">
          <AccountSection title="Research notes" description="Where facts came from, conflicts between sources and follow-ups. Admins only -- invited viewers never see this tab.">
            <p class="text-sm leading-relaxed text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ candidate.notes }}</p>
          </AccountSection>
        </div>
      </div>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import AccountSection from '@/Components/Admin/Accounts/AccountSection.vue'
import IconButton from '@/Components/IconButton.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import CandidatePhoto from '../Partials/CandidatePhoto.vue'
import EntryCard from '../Partials/EntryCard.vue'
import FactList from '../Partials/FactList.vue'
import { newPillClass } from '../Partials/classes'
import { formatDate, hostOf, isHttpUrl, useReadOnly } from '../Partials/format'
import { toUnix, useSeen } from '../Partials/seen'
import type { Article, Entry, Options, Portfolio } from '../Partials/types'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  portfolio: Portfolio
  /** about, a portfolio section key, or news -- from ?tab=, checked server-side. */
  activeTab: string
  options: Options
}>()

// Computed: Inertia replaces the prop object on every visit.
const candidate = computed(() => props.portfolio.candidate)
const readOnly = useReadOnly()
const { confirmDialog } = useConfirmDialog()

const statusBadgeClass = computed(() => ({
  nominated: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  declared: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
  withdrawn: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}[candidate.value.status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'))

const contactFacts = computed(() => {
  const c = candidate.value
  const list: { label: string; text: string; href: string; external: boolean }[] = []
  if (isHttpUrl(c.website)) list.push({ label: 'Website', text: hostOf(c.website), href: c.website, external: true })
  if (isHttpUrl(c.facebook_url)) list.push({ label: 'Facebook', text: 'Campaign page', href: c.facebook_url, external: true })
  if (isHttpUrl(c.instagram_url)) list.push({ label: 'Instagram', text: 'Profile', href: c.instagram_url, external: true })
  if (c.email) list.push({ label: 'Email', text: c.email, href: `mailto:${c.email}`, external: false })
  if (c.phone) list.push({ label: 'Phone', text: c.phone, href: `tel:${c.phone}`, external: false })
  return list
})

const backgroundDescriptions: Record<string, string> = {
  career: 'Work, profession and employers.',
  business: 'Businesses they own or run.',
  education: 'Schooling and credentials.',
  community: 'Boards, volunteering and community groups.',
  public_service: 'Elected and appointed roles, committees and past campaigns.',
  local_roots: 'Their time in Salmon Arm and the Shuswap.',
}

// ---- New since last visit (per-viewer cookie) ----
// Snapshot when the viewer last opened this candidate, then mark it seen:
// items newer than the snapshot stay tagged "New" for this visit. Switching
// tabs keeps the page component (preserve-state), so the snapshot survives.
// useSeen reads the cookie in its own onMounted, registered (so run) first.
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
const tabs = computed(() => [
  { id: 'about', title: 'About', count: backgroundEntries.value.length, hasNew: backgroundEntries.value.some(isNewEntry) },
  ...props.portfolio.sections.map((s) => {
    const entries = sectionEntries(s.key)
    return { id: s.key, title: s.title, count: entries.length, hasNew: entries.some(isNewEntry) }
  }),
  ...(props.portfolio.articles.length
    ? [{ id: 'news', title: 'In the news', count: props.portfolio.articles.length, hasNew: props.portfolio.articles.some(isNewArticle) }]
    : []),
  ...(!readOnly.value && candidate.value.notes ? [{ id: 'notes', title: 'Research notes', count: 0, hasNew: false }] : []),
])

const activeSection = computed(() => props.portfolio.sections.find((s) => s.key === props.activeTab) ?? null)

const tabHref = (id: string) => route('admin.elections.candidates.show', id === 'about' ? candidate.value.slug : { candidate: candidate.value.slug, tab: id })

const switchTab = (id: string) => {
  if (id !== props.activeTab) router.get(tabHref(id), {}, { preserveState: true, preserveScroll: true })
}

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
