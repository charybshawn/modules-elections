<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Progress scoresheet" :href="route('admin.elections.plans.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6 print:hidden" />
      <Link :href="route('admin.elections.plans.index')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm mb-4 print:hidden">← City plans</Link>

      <article class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10 print:shadow-none print:ring-0">
        <header class="bg-gradient-to-br from-emerald-600 to-teal-600 px-6 py-7 sm:px-8 text-white print:bg-none print:text-gray-900 print:px-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-emerald-100 print:text-gray-500">City of Salmon Arm</p>
          <h1 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight">{{ sheet.title }}</h1>
          <p class="mt-1 text-sm text-emerald-100 print:text-gray-600">Checked {{ formatDate(sheet.as_of) }} against the public record</p>
          <p v-if="wasUpdated" class="mt-3 inline-block rounded-full bg-orange-500 px-3 py-1 text-sm font-semibold text-white print:hidden">Updated since your last visit</p>
          <div class="mt-4 flex flex-wrap gap-2 print:hidden">
            <Link :href="route('admin.elections.plans.show', sheet.plan_slug)" class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25">← The plan itself</Link>
            <button type="button" class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25" @click="print">Print</button>
          </div>
        </header>

        <div class="px-6 py-6 sm:px-8 print:px-0">
          <!-- Scoreboard -->
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div v-for="item in sheet.scale" :key="item.key" :class="['rounded-lg p-3 ring-1', tone(item.key).card]">
              <dd class="text-2xl font-bold">{{ sheet.tally[item.key] }}</dd>
              <dt class="mt-0.5 text-xs font-semibold">{{ item.label }}</dt>
              <p class="mt-1 text-[11px] leading-snug opacity-80">{{ item.help }}</p>
            </div>
          </dl>
          <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            Of {{ sheet.tally.total }} projects, {{ sheet.tally.behind }} are running behind the plan's own window for them.
          </p>

          <div class="mt-5 space-y-3 text-[15px] leading-relaxed text-gray-800 dark:text-gray-200">
            <p v-for="para in sheet.summary" :key="para">{{ para }}</p>
          </div>

          <!-- Scoresheet -->
          <section v-for="group in sheet.groups" :key="group.label" class="mt-9">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
              <span class="h-4 w-1 rounded-full bg-emerald-500" aria-hidden="true" />{{ group.label }} · {{ group.years }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ group.note }}</p>

            <div class="mt-3 space-y-3">
              <details v-for="project in group.projects" :key="project.name" class="group rounded-lg ring-1 ring-gray-900/10 dark:ring-white/10 open:bg-gray-50/60 dark:open:bg-gray-900/30" :open="hasDetail(project) && openAll">
                <summary class="flex cursor-pointer list-none items-start gap-3 px-4 py-3">
                  <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ project.name }} <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">{{ project.tag }}</span></span>
                    <span class="block text-sm text-gray-700 dark:text-gray-300">{{ project.headline }}</span>
                  </span>
                  <span class="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-start">
                    <span v-if="project.timing === 'behind'" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800 dark:bg-rose-500/20 dark:text-rose-200">Behind plan window</span>
                    <span v-else-if="project.timing === 'within'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200">Within window</span>
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', tone(project.status).chip]">{{ label(project.status) }}</span>
                  </span>
                </summary>

                <div v-if="hasDetail(project)" class="grid grid-cols-1 gap-4 border-t border-gray-200 px-4 py-4 text-sm leading-relaxed dark:border-gray-700 md:grid-cols-3">
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Done so far</h3>
                    <ul class="mt-1 space-y-1.5 text-gray-800 dark:text-gray-200"><li v-for="line in project.done" :key="line">{{ line }}</li><li v-if="!project.done.length" class="text-gray-500">Nothing found.</li></ul>
                  </div>
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">In the works</h3>
                    <ul class="mt-1 space-y-1.5 text-gray-800 dark:text-gray-200"><li v-for="line in project.next" :key="line">{{ line }}</li><li v-if="!project.next.length" class="text-gray-500">Nothing found.</li></ul>
                  </div>
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Challenges</h3>
                    <ul class="mt-1 space-y-1.5 text-gray-800 dark:text-gray-200"><li v-for="line in project.challenges" :key="line">{{ line }}</li><li v-if="!project.challenges.length" class="text-gray-500">Nothing found.</li></ul>
                  </div>
                  <p v-if="project.sources.length" class="text-xs text-gray-600 dark:text-gray-400 md:col-span-3">
                    Sources:
                    <template v-for="(source, i) in project.sources" :key="source.url">
                      <a :href="source.url" target="_blank" rel="noopener" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ source.label }}</a><span v-if="source.date"> ({{ source.date }})</span><span v-if="i < project.sources.length - 1">; </span>
                    </template>
                  </p>
                </div>
              </details>
            </div>
          </section>

          <button type="button" class="mt-4 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400 print:hidden" @click="openAll = !openAll">{{ openAll ? 'Collapse all' : 'Expand all' }}</button>

          <!-- Capital spending -->
          <section class="mt-10 break-inside-avoid">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
              <span class="h-4 w-1 rounded-full bg-emerald-500" aria-hidden="true" />Major capital spending, 2022 to 2031
            </h2>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ sheet.capex.intro }}</p>

            <div class="mt-4 space-y-3">
              <div v-for="item in sheet.capex.items" :key="item.name" class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-900/5 dark:bg-gray-900/40 dark:ring-white/5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.name }}</h3>
                  <p class="text-sm font-bold text-gray-900 dark:text-white">{{ item.amount }}</p>
                </div>
                <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm text-gray-700 dark:text-gray-300 sm:grid-cols-[7rem_1fr]">
                  <dt class="font-medium text-gray-500 dark:text-gray-400">Timing</dt><dd>{{ item.timing }}</dd>
                  <dt class="font-medium text-gray-500 dark:text-gray-400">Funding</dt><dd>{{ item.funding }}</dd>
                  <template v-if="item.note"><dt class="font-medium text-gray-500 dark:text-gray-400">Note</dt><dd>{{ item.note }}</dd></template>
                </dl>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                  Sources:
                  <template v-for="(source, i) in item.sources" :key="source.url">
                    <a :href="source.url" target="_blank" rel="noopener" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ source.label }}</a><span v-if="source.date"> ({{ source.date }})</span><span v-if="i < item.sources.length - 1">; </span>
                  </template>
                </p>
              </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">{{ sheet.capex.other_heading }}</h3>
            <ul class="mt-2 space-y-2 text-sm text-gray-800 dark:text-gray-200">
              <li v-for="row in sheet.capex.other" :key="row.text" class="flex gap-3">
                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
                <span>{{ row.text }}
                  <template v-for="source in row.sources" :key="source.url"><a :href="source.url" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">[{{ source.date }}]</a> </template>
                </span>
              </li>
            </ul>

            <div class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-400/20">
              <h3 class="font-semibold">What wasn't found</h3>
              <ul class="mt-1 space-y-1"><li v-for="gap in sheet.capex.gaps" :key="gap">{{ gap }}</li></ul>
            </div>
          </section>

          <!-- Method -->
          <section class="mt-10 rounded-lg bg-gray-50 p-4 ring-1 ring-gray-900/5 dark:bg-gray-900/40 dark:ring-white/5 break-inside-avoid">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">How this was put together</h2>
            <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-300"><li v-for="line in sheet.method" :key="line">{{ line }}</li></ul>
          </section>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { formatDate } from '../Partials/format'
import { useUpdates } from '../Partials/updates'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type Source = { label: string; url: string; date: string | null }
type Project = {
  name: string
  tag: string
  status: string
  timing: 'behind' | 'within' | null
  headline: string
  done: string[]
  next: string[]
  challenges: string[]
  sources: Source[]
}

const props = defineProps<{
  sheet: {
    slug: string
    plan_slug: string
    title: string
    as_of: string
    scale: { key: string; label: string; help: string }[]
    tally: Record<string, number>
    summary: string[]
    groups: { label: string; years: string; note: string; projects: Project[] }[]
    capex: {
      intro: string
      items: { name: string; amount: string; timing: string; funding: string; note: string; sources: Source[] }[]
      other_heading: string
      other: { text: string; sources: Source[] }[]
      gaps: string[]
    }
    method: string[]
  }
}>()

// Opening the scoresheet marks it seen; the "Updated" ribbon stays for this visit only.
const updates = useUpdates()
const wasUpdated = ref(false)
const openAll = ref(false)
const openSheet = async () => {
  await updates.ensure()
  wasUpdated.value = updates.planUnread(props.sheet.slug)
  updates.mark(`plans:${props.sheet.slug}`)
}
onMounted(openSheet)
watch(() => props.sheet.slug, openSheet)

const hasDetail = (project: Project) => project.done.length + project.next.length + project.challenges.length > 0

const tones: Record<string, { card: string; chip: string }> = {
  complete: { card: 'bg-emerald-50 text-emerald-900 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-100 dark:ring-emerald-400/20', chip: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' },
  underway: { card: 'bg-sky-50 text-sky-900 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-400/20', chip: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' },
  planning: { card: 'bg-violet-50 text-violet-900 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-100 dark:ring-violet-400/20', chip: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200' },
  paused: { card: 'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-400/20', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200' },
  unknown: { card: 'bg-gray-100 text-gray-800 ring-gray-200 dark:bg-gray-700/40 dark:text-gray-100 dark:ring-white/10', chip: 'bg-gray-200 text-gray-800 dark:bg-gray-600/40 dark:text-gray-200' },
}
const tone = (key: string) => tones[key] ?? tones.unknown
const label = (key: string) => props.sheet.scale.find((item) => item.key === key)?.label ?? key

const print = () => window.print()
</script>
