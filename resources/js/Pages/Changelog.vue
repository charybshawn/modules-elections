<template>
  <AdminShowShell wide>
    <template #mobile-header>
      <AdminMobileHeader title="What's new" :href="route('admin.elections.home')" />
    </template>

    <template #header>
      <Link :href="route('admin.elections.home')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">
        &larr; Back to Elections
      </Link>
      <h1 class="hidden md:block mt-2 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">What's new on the site</h1>
    </template>

    <div class="space-y-6 md:mt-6">
      <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-300">What has changed on the election tracker, newest first. The daily update email carries the same list.</p>

      <p v-if="!entries.length" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">Nothing is on the log yet.</p>

      <template v-else>
        <!-- Filter by the page a change belongs to, and choose the order. -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Show changes to">
            <button type="button" :aria-pressed="page === null" :class="chipClass(page === null)" class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors" @click="page = null">All {{ entries.length }}</button>
            <button v-for="p in pageChips" :key="p.key" type="button" :aria-pressed="page === p.key" :class="chipClass(page === p.key)" class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors" @click="page = page === p.key ? null : p.key">{{ p.label }} {{ p.count }}</button>
          </div>
          <div class="flex shrink-0 items-center gap-1.5" role="group" aria-label="Order">
            <button type="button" :aria-pressed="order === 'newest'" :class="chipClass(order === 'newest')" class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors" @click="order = 'newest'">Newest</button>
            <button type="button" :aria-pressed="order === 'impact'" :class="chipClass(order === 'impact')" class="tap-target-touch rounded-full px-3 py-1 text-xs font-semibold transition-colors" @click="order = 'impact'">Biggest effect</button>
          </div>
        </div>

        <div v-for="group in groups" :key="group.heading" class="grid grid-cols-1 gap-x-8 gap-y-2 md:grid-cols-[10rem_1fr]">
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 md:pt-3">{{ group.heading }}</h2>
          <ul class="space-y-2">
            <li v-for="e in group.entries" :key="e.id" class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
              <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 font-semibold text-gray-900 dark:text-white">
                {{ e.title }}
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ pages[e.page] ?? e.page }}</span>
                <span class="inline-flex items-center gap-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400" :title="`Effect on the experience: ${impacts[e.impact] ?? e.impact}`">
                  <span v-for="n in 5" :key="n" :class="n <= e.impact ? 'bg-indigo-500' : 'bg-gray-200 dark:bg-gray-600'" class="inline-block h-1.5 w-3 rounded-sm" aria-hidden="true" />
                  <span class="sr-only">Effect: {{ impacts[e.impact] ?? e.impact }}</span>
                </span>
              </p>
              <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ e.body }}</p>
              <a v-if="e.url && isHttpUrl(e.url)" :href="e.url" class="mt-1 inline-block text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">See it</a>
            </li>
          </ul>
        </div>
        <p v-if="!filtered.length" class="text-sm text-gray-500 dark:text-gray-400">Nothing on the log for that page.</p>
      </template>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { trail } from './Partials/breadcrumbs'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import { formatDate, isHttpUrl } from './Partials/format'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true, breadcrumbs: trail({ label: "What's new on the site" }) }, () => page) })

type Entry = { id: number; day: string; title: string; body: string; scope: string; page: string; impact: number; url: string | null }

const props = defineProps<{ entries: Entry[]; scopes: Record<string, string>; pages: Record<string, string>; impacts: Record<number, string> }>()

const page = ref<string | null>(null)
const order = ref<'newest' | 'impact'>('newest')

const chipClass = (on: boolean) =>
  on ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'

// One chip per page that has a change, in the order the pages are listed.
const pageChips = computed(() => {
  const counts = new Map<string, number>()
  for (const e of props.entries) counts.set(e.page, (counts.get(e.page) ?? 0) + 1)
  return Object.keys(props.pages).filter((k) => counts.has(k)).map((key) => ({ key, label: props.pages[key], count: counts.get(key) as number }))
})

const filtered = computed(() => (page.value ? props.entries.filter((e) => e.page === page.value) : props.entries))

// Newest: grouped under each day. Biggest effect: one ranked list, ties newest first (the entries arrive newest first).
const groups = computed(() => {
  if (order.value === 'impact') {
    const ranked = [...filtered.value].sort((a, b) => b.impact - a.impact)
    return ranked.length ? [{ heading: 'Biggest effect first', entries: ranked }] : []
  }
  const out: { heading: string; entries: Entry[] }[] = []
  for (const e of filtered.value) {
    const heading = formatDate(e.day)
    const last = out[out.length - 1]
    if (last && last.heading === heading) last.entries.push(e)
    else out.push({ heading, entries: [e] })
  }
  return out
})
</script>
