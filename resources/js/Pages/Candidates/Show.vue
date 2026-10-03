<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader :title="candidate.name" :href="route('admin.elections.index')" />

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 sm:rounded-lg sm:shadow-sm">
        <!-- Header -->
        <header class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700">
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

          <!-- On this page -->
          <nav v-if="toc.length > 1" class="mt-5 flex flex-wrap gap-2" aria-label="Sections">
            <a
              v-for="item in toc"
              :key="item.id"
              :href="`#${item.id}`"
              class="tap-target-touch rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >{{ item.title }}</a>
          </nav>
        </header>

        <div class="p-4 sm:p-6 space-y-10">
          <div v-if="!readOnly && candidate.notes" class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
            <div class="font-medium">Research notes <span class="font-normal text-amber-700 dark:text-amber-300/70">(admins only)</span></div>
            <p class="mt-1 whitespace-pre-line">{{ candidate.notes }}</p>
          </div>

          <!-- Bio -->
          <section id="bio">
            <h2 :class="headingClass">About</h2>
            <template v-if="candidate.bio">
              <div class="mt-3 prose prose-sm sm:prose-base max-w-none dark:prose-invert whitespace-pre-line">{{ candidate.bio }}</div>
              <p v-if="isHttpUrl(candidate.bio_source_url)" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Source: <a :href="candidate.bio_source_url" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2">{{ hostOf(candidate.bio_source_url) }}</a>
              </p>
            </template>
            <p v-else-if="!portfolio.background.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No bio on file yet.</p>

            <div v-if="portfolio.background.length" class="mt-6 space-y-8">
              <div v-for="group in portfolio.background" :key="group.topic ?? 'all'">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ options.backgroundTopics[group.topic ?? ''] ?? group.topic }}</h3>
                <ul class="mt-3 space-y-5">
                  <li v-for="entry in group.entries" :key="entry.id">
                    <EntryCard :entry="entry" :options="options" :editable="!readOnly" @delete="deleteEntry" />
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <!-- Portfolio sections -->
          <section v-for="section in portfolio.sections" :id="section.key" :key="section.key">
            <h2 :class="headingClass">{{ section.title }}</h2>
            <div class="mt-4 space-y-8">
              <div v-for="group in section.groups" :key="group.topic ?? 'all'">
                <h3 v-if="group.topic" class="text-sm font-semibold text-gray-900 dark:text-white">{{ options.topics[group.topic] ?? group.topic }}</h3>
                <ul class="mt-3 space-y-5">
                  <li v-for="entry in group.entries" :key="entry.id">
                    <EntryCard
                      :entry="entry"
                      :options="options"
                      :editable="!readOnly"
                      :show-kind="section.key === 'words'"
                      @delete="deleteEntry"
                    />
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <p v-if="portfolio.sections.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            Nothing on their platform or statements is on file yet -- the next research import will fill this in.
          </p>

          <!-- News -->
          <section v-if="portfolio.articles.length" id="news">
            <h2 :class="headingClass">In the news</h2>
            <ul class="mt-4 space-y-4">
              <li v-for="article in portfolio.articles" :key="article.id">
                <a v-if="isHttpUrl(article.url)" :href="article.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 hover:underline dark:text-white">{{ article.title }}</a>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ [article.outlet, formatDate(article.published_on)].filter(Boolean).join(' · ') }}</div>
                <p v-if="article.summary" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ article.summary }}</p>
                <button v-if="!readOnly" type="button" class="tap-target-touch mt-1 text-xs text-red-600 hover:text-red-800 dark:text-red-400" @click="deleteArticle(article)">Remove article</button>
              </li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import CandidatePhoto from '../Partials/CandidatePhoto.vue'
import EntryCard from '../Partials/EntryCard.vue'
import { secondaryButtonClass } from '../Partials/classes'
import { formatDate, hostOf, isHttpUrl, useReadOnly } from '../Partials/format'
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

const headingClass = 'text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2'

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

const toc = computed(() => [
  { id: 'bio', title: 'About' },
  ...props.portfolio.sections.map((s) => ({ id: s.key, title: s.title })),
  ...(props.portfolio.articles.length ? [{ id: 'news', title: 'In the news' }] : []),
])

// ---- Entries ----
const deleteEntry = async (entry: Entry) => {
  const confirmed = await confirmDialog({
    title: 'Delete entry',
    message: `Delete "${entry.summary.slice(0, 80)}${entry.summary.length > 80 ? '…' : ''}"? A later import would bring it back if the research file still has it.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) {
    router.delete(route('admin.elections.entries.destroy', [candidate.value.slug, entry.id]), { preserveScroll: true })
  }
}

const deleteArticle = async (article: Article) => {
  const confirmed = await confirmDialog({
    title: 'Remove article',
    message: `Remove "${article.title}" from every candidate it's linked to?`,
    confirmLabel: 'Remove',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.elections.articles.destroy', article.id), { preserveScroll: true })
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
