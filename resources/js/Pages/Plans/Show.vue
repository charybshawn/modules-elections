<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader :title="plan.title" :href="route('admin.elections.plans.index')" />

    <div class="px-4 sm:px-0 max-w-4xl mx-auto">
      <ElectionsNav class="mb-6 print:hidden" />
      <Link :href="route('admin.elections.plans.index')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm mb-4 print:hidden">← City plans</Link>

      <article class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10 print:shadow-none print:ring-0">
        <!-- Header band -->
        <header class="bg-gradient-to-br from-indigo-600 to-sky-600 px-6 py-7 sm:px-8 text-white print:bg-none print:text-gray-900 print:px-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-indigo-100 print:text-gray-500">City of Salmon Arm</p>
          <h1 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight">{{ plan.title }}</h1>
          <p class="mt-1 text-sm text-indigo-100 print:text-gray-600">{{ plan.status }}</p>
          <p v-if="wasUpdated" class="mt-3 inline-block rounded-full bg-orange-500 px-3 py-1 text-sm font-semibold text-white print:hidden">Updated since your last visit</p>
          <div class="mt-4 flex flex-wrap gap-2 print:hidden">
            <a :href="plan.source_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25">Read the full plan (PDF) ↗</a>
            <a :href="plan.page_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25">City web page ↗</a>
            <Link v-if="progressSlug" :href="route('admin.elections.plans.progress', progressSlug)" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
              Progress scoresheet →
              <span v-if="updates.planUnread(progressSlug)" class="rounded-full bg-orange-500 px-2 py-0.5 text-xs font-semibold text-white">Updated</span>
            </Link>
            <button type="button" class="inline-flex items-center rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium hover:bg-white/25" @click="print">Print</button>
          </div>
        </header>

        <div class="px-6 py-6 sm:px-8 print:px-0">
          <!-- Key numbers -->
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="stat in plan.stats" :key="stat.label" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-3 ring-1 ring-gray-900/5 dark:ring-white/5">
              <dd class="text-xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</dd>
              <dt class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ stat.label }} <PageRefs :pages="stat.pages" :url="plan.source_url" /></dt>
            </div>
          </dl>

          <!-- Summary -->
          <div class="mt-6 space-y-3 text-[15px] leading-relaxed text-gray-800 dark:text-gray-200">
            <p v-for="(para, i) in plan.summary" :key="i">{{ para.text }} <PageRefs :pages="para.pages" :url="plan.source_url" /></p>
          </div>

          <section v-for="section in plan.sections" :key="section.heading" class="mt-9 break-inside-avoid">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
              <span class="h-4 w-1 rounded-full bg-indigo-500" aria-hidden="true" />{{ section.heading }}
            </h2>
            <p v-if="section.intro" class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ section.intro.text }} <PageRefs :pages="section.intro.pages" :url="plan.source_url" /></p>

            <!-- list -->
            <component :is="section.ordered ? 'ol' : 'ul'" v-if="section.layout === 'list'" class="mt-3 space-y-2">
              <li v-for="(point, i) in section.points" :key="point.text" class="flex gap-3 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                <span v-if="section.ordered" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ i + 1 }}</span>
                <span v-else class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-hidden="true" />
                <span>{{ point.text }} <PageRefs :pages="point.pages" :url="plan.source_url" /></span>
              </li>
            </component>

            <!-- quote -->
            <blockquote v-else-if="section.layout === 'quote'" class="mt-3 border-l-4 border-indigo-300 dark:border-indigo-500/60 bg-indigo-50/60 dark:bg-indigo-500/10 px-4 py-3 text-sm italic leading-relaxed text-gray-800 dark:text-gray-200 rounded-r-lg">
              <template v-for="point in section.points" :key="point.text">{{ point.text }} <PageRefs :pages="point.pages" :url="plan.source_url" class="not-italic" /></template>
            </blockquote>

            <!-- cards -->
            <div v-else-if="section.layout === 'cards'" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <div v-for="(point, i) in section.points" :key="point.title" :class="['rounded-lg p-4 ring-1', cardTone(i)]">
                <h3 class="text-sm font-semibold">{{ point.title }}</h3>
                <p class="mt-1 text-sm leading-snug opacity-90">{{ point.text }}</p>
                <PageRefs :pages="point.pages" :url="plan.source_url" class="mt-2 block" />
              </div>
            </div>

            <!-- term groups -->
            <div v-else-if="section.layout === 'groups'" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
              <div v-for="(group, i) in section.groups" :key="group.label" class="rounded-lg ring-1 ring-gray-900/5 dark:ring-white/10 overflow-hidden">
                <div :class="['px-4 py-2.5', termTone(i)]">
                  <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                  <p class="text-xs opacity-80">{{ group.years }} · {{ group.items.length }} projects <PageRefs :pages="group.pages" :url="plan.source_url" /></p>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                  <li v-for="item in group.items" :key="item.text" class="flex items-start justify-between gap-2 px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                    <span>{{ item.text }}</span>
                    <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', tagTone(item.tag)]">{{ item.tag }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- topics -->
            <div v-else-if="section.layout === 'topics'" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div v-for="point in section.points" :key="point.title" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ point.title }}</h3>
                <p class="mt-1 text-sm leading-snug text-gray-700 dark:text-gray-300">{{ point.text }} <PageRefs :pages="point.pages" :url="plan.source_url" /></p>
              </div>
            </div>

            <!-- timeline -->
            <ol v-else-if="section.layout === 'timeline'" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <li v-for="(point, i) in section.points" :key="point.title" class="relative rounded-lg p-4 text-center ring-1 ring-emerald-900/10 dark:ring-emerald-400/20" :style="{ background: `color-mix(in srgb, rgb(16 185 129) ${12 + i * 10}%, transparent)` }">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">{{ point.title }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ point.text }}</p>
                <PageRefs :pages="point.pages" :url="plan.source_url" class="mt-1 block" />
              </li>
            </ol>
          </section>

          <!-- Sources -->
          <section class="mt-10 rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5 break-inside-avoid">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Sources</h2>
            <ul class="mt-2 space-y-1 text-sm">
              <li><a :href="plan.source_url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ plan.source_label }}</a> <span class="text-gray-500 dark:text-gray-400">· City of Salmon Arm</span></li>
              <li><a :href="plan.page_url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ plan.title }} web page</a> <span class="text-gray-500 dark:text-gray-400">· City of Salmon Arm</span></li>
            </ul>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
              A plain-language summary of the City's document, read {{ formatDate(plan.read_on) }}. Every "p." link opens the City's PDF at that page. Where this sheet and the document differ, the document governs.
            </p>
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
import PageRefs from './PageRefs.vue'
import { formatDate } from '../Partials/format'
import { useUpdates } from '../Partials/updates'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type Cited = { text: string; pages: number[] }
type Point = Cited & { title?: string }
type PlanSection = {
  heading: string
  layout: 'list' | 'quote' | 'cards' | 'groups' | 'topics' | 'timeline'
  intro?: Cited
  ordered?: boolean
  points?: Point[]
  groups?: { label: string; years: string; pages: number[]; items: { text: string; tag: string }[] }[]
}

const props = defineProps<{
  progressSlug: string | null
  plan: {
    slug: string
    title: string
    status: string
    source_url: string
    source_label: string
    page_url: string
    read_on: string
    stats: { value: string; label: string; pages: number[] }[]
    summary: Cited[]
    sections: PlanSection[]
  }
}>()

// Opening a sheet marks it seen; "Updated since your last visit" stays up for this visit only.
const updates = useUpdates()
const wasUpdated = ref(false)
const openSheet = async () => {
  await updates.ensure()
  wasUpdated.value = updates.planUnread(props.plan.slug)
  updates.mark(`plans:${props.plan.slug}`)
}
onMounted(openSheet)
watch(() => props.plan.slug, openSheet)

const cardTones = [
  'bg-sky-50 text-sky-900 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-400/20',
  'bg-violet-50 text-violet-900 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-100 dark:ring-violet-400/20',
  'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-400/20',
  'bg-emerald-50 text-emerald-900 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-100 dark:ring-emerald-400/20',
  'bg-rose-50 text-rose-900 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-100 dark:ring-rose-400/20',
]
const cardTone = (i: number) => cardTones[i % cardTones.length]

const termTones = [
  'bg-violet-100 text-violet-900 dark:bg-violet-500/20 dark:text-violet-100',
  'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100',
  'bg-rose-100 text-rose-900 dark:bg-rose-500/20 dark:text-rose-100',
]
const termTone = (i: number) => termTones[i % termTones.length]

const tagTone = (tag: string) =>
  ({
    Capital: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    Plan: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200',
    Operational: 'bg-gray-200 text-gray-800 dark:bg-gray-600/40 dark:text-gray-200',
  })[tag] ?? 'bg-gray-100 text-gray-700'

const print = () => window.print()
</script>
