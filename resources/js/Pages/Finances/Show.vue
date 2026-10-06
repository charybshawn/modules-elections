<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="City finances" :href="route('admin.elections.index')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <ElectionsNav class="mb-6 print:hidden" />

      <article class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10 print:shadow-none print:ring-0">
        <header class="bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-7 sm:px-8 text-white print:bg-none print:text-gray-900 print:px-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-emerald-100 print:text-gray-500">City of Salmon Arm</p>
          <h1 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight">{{ f.title }}</h1>
          <p class="mt-1 text-sm text-emerald-50 print:text-gray-600">{{ f.subtitle }}</p>
          <p class="mt-3 text-xs text-emerald-100 print:text-gray-500">Figures are the City's audited {{ f.year }} results unless marked otherwise. Read {{ formatDate(f.read_on) }}.</p>
        </header>

        <div class="px-6 py-6 sm:px-8 print:px-0 space-y-10">
          <!-- Key numbers -->
          <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="stat in f.stats" :key="stat.label" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-3 ring-1 ring-gray-900/5 dark:ring-white/5">
              <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</dd>
              <dt class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ stat.label }} <Cite :keys="stat.sources" :index="sourceIndex" /></dt>
            </div>
          </dl>

          <!-- Money in -->
          <section class="break-inside-avoid">
            <SectionHeading>Where the money comes from</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ money(f.revenue.total) }} in {{ f.year }} <Cite :keys="f.revenue.sources" :index="sourceIndex" /></p>
            <StackedBar :items="f.revenue.items" :total="f.revenue.total" class="mt-4" />
            <ul class="mt-4 grid grid-cols-1 gap-x-8 gap-y-1.5 sm:grid-cols-2">
              <li v-for="(item, i) in f.revenue.items" :key="item.label" class="flex items-center gap-2 text-sm">
                <span :class="['h-3 w-3 shrink-0 rounded-sm', swatch(i)]" aria-hidden="true" />
                <span class="min-w-0 flex-1 text-gray-800 dark:text-gray-200">{{ item.label }}</span>
                <span class="tabular-nums text-gray-900 dark:text-white font-medium">{{ money(item.amount) }}</span>
                <span class="w-12 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ pct(item.amount, f.revenue.total) }}</span>
              </li>
            </ul>
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ f.revenue.note }}</p>
          </section>

          <!-- Money out -->
          <section class="break-inside-avoid">
            <SectionHeading>Where it goes</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ money(f.expenses.total) }} spent on services in {{ f.year }} <Cite :keys="f.expenses.sources" :index="sourceIndex" /></p>
            <ul class="mt-4 space-y-2.5">
              <li v-for="item in f.expenses.items" :key="item.label" class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-3 text-sm" :title="`${item.label}: ${money(item.amount)} (${pct(item.amount, f.expenses.total)})`">
                <span class="truncate text-gray-800 dark:text-gray-200">{{ item.label }}</span>
                <span class="h-5 rounded-r bg-gray-100 dark:bg-gray-900/50"><span class="block h-5 rounded-r bg-[#2a78d6] dark:bg-[#3987e5]" :style="{ width: barWidth(item.amount, maxExpense) }" /></span>
                <span class="w-28 text-right tabular-nums text-gray-900 dark:text-white"><span class="font-medium">{{ money(item.amount) }}</span> <span class="text-gray-500 dark:text-gray-400">{{ pct(item.amount, f.expenses.total) }}</span></span>
              </li>
            </ul>
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ f.expenses.note }}</p>
          </section>

          <!-- Tax bill -->
          <section class="break-inside-avoid">
            <SectionHeading>Your property tax bill</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ money(f.tax_bill.total) }} collected in {{ f.year }}, and who it went to <Cite :keys="f.tax_bill.sources" :index="sourceIndex" /></p>
            <StackedBar :items="f.tax_bill.items" :total="f.tax_bill.total" class="mt-4" :labels="true" />
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ f.tax_bill.note }}</p>
          </section>

          <!-- Balance sheet -->
          <section class="break-inside-avoid">
            <SectionHeading>Debt, savings and the state of the assets</SectionHeading>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div v-for="b in f.balance" :key="b.label" class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ b.label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ b.value }}</p>
                <p class="mt-1 text-sm leading-snug text-gray-700 dark:text-gray-300">{{ b.detail }} <Cite :keys="b.sources" :index="sourceIndex" /></p>
              </div>
            </div>
          </section>

          <!-- 2026 budget -->
          <section class="break-inside-avoid">
            <SectionHeading>How the 2026 budget was balanced</SectionHeading>
            <ul class="mt-3 space-y-2">
              <li v-for="item in f.budget_2026" :key="item.text" class="flex gap-3 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true" />
                <span>{{ item.text }} <Cite :keys="item.sources" :index="sourceIndex" /></span>
              </li>
            </ul>
          </section>

          <!-- Projects -->
          <section class="break-inside-avoid">
            <SectionHeading>The big bills ahead</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Published cost estimates for major projects, next to the City's entire debt today.</p>
            <ul class="mt-4 space-y-4">
              <li class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-3 text-sm">
                <span class="text-gray-500 dark:text-gray-400 italic">{{ f.projects.reference.label }}</span>
                <span class="h-5"><span class="block h-5 rounded-r bg-gray-300 dark:bg-gray-600" :style="{ width: barWidth(f.projects.reference.amount, maxProject) }" /></span>
                <span class="w-20 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ money(f.projects.reference.amount) }}</span>
              </li>
              <li v-for="p in f.projects.items" :key="p.label" :title="`${p.label}: about ${money(p.amount)}`">
                <div class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-3 text-sm">
                  <span class="font-medium text-gray-900 dark:text-white">{{ p.label }}</span>
                  <span class="h-5"><span class="block h-5 rounded-r bg-[#eb6834] dark:bg-[#d95926]" :style="{ width: barWidth(p.amount, maxProject) }" /></span>
                  <span class="w-20 text-right tabular-nums font-medium text-gray-900 dark:text-white">~{{ money(p.amount) }}</span>
                </div>
                <p class="mt-1 text-xs leading-snug text-gray-600 dark:text-gray-400 sm:pl-[14.75rem]"><span class="font-medium text-gray-700 dark:text-gray-300">{{ p.term }}.</span> {{ p.detail }} <Cite :keys="p.sources" :index="sourceIndex" /></p>
              </li>
            </ul>
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
              {{ f.projects.note.split('City plans')[0] }}<Link :href="route('admin.elections.plans.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">City plans</Link>{{ f.projects.note.split('City plans')[1] }}
            </p>
          </section>

          <!-- Scenarios -->
          <section class="break-inside-avoid">
            <SectionHeading>If the big projects were borrowed for</SectionHeading>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Yearly debt payments as a share of revenue. The Province caps this at {{ f.scenarios.limit_pct }}%. <Cite :keys="f.scenarios.sources" :index="sourceIndex" /></p>

            <div class="mt-4 space-y-3">
              <div v-for="row in scenarioRows" :key="row.label" class="grid grid-cols-[minmax(0,14rem)_1fr_auto] items-center gap-3 text-sm" :title="`${row.label}: about ${money(row.annual)} a year, ${row.pct}% of revenue`">
                <span class="text-gray-800 dark:text-gray-200">{{ row.label }}</span>
                <span class="relative h-6 rounded-r bg-gray-100 dark:bg-gray-900/50">
                  <span :class="['block h-6 rounded-r', row.pct > f.scenarios.limit_pct ? 'bg-[#d03b3b]' : 'bg-[#2a78d6] dark:bg-[#3987e5]']" :style="{ width: `${(row.pct / scaleMax) * 100}%` }" />
                  <span class="absolute inset-y-[-4px] w-0.5 bg-gray-900 dark:bg-white" :style="{ left: `${(f.scenarios.limit_pct / scaleMax) * 100}%` }" aria-hidden="true" />
                </span>
                <span class="w-32 text-right tabular-nums">
                  <span class="font-semibold text-gray-900 dark:text-white">{{ row.pct }}%</span>
                  <span v-if="row.pct > f.scenarios.limit_pct" class="ml-1 text-xs font-medium text-[#d03b3b]">⚠ over the cap</span>
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 sm:pl-[14.75rem]">The black line marks the {{ f.scenarios.limit_pct }}% provincial limit. Each row adds one project to the one above.</p>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div v-for="p in f.scenarios.payments" :key="p.label" class="rounded-lg p-4 ring-1 ring-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:ring-amber-400/20">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ p.label }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ money(p.annual) }}<span class="text-sm font-medium text-gray-600 dark:text-gray-300"> a year</span></p>
                <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">That's {{ p.compare }}.</p>
              </div>
            </div>
            <p class="mt-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ f.scenarios.rule_of_thumb }}</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ f.scenarios.assumptions }}</p>
          </section>

          <!-- Analysis -->
          <section class="break-inside-avoid rounded-xl ring-1 ring-indigo-200 dark:ring-indigo-400/30 bg-indigo-50/50 dark:bg-indigo-500/5 p-5">
            <div class="flex flex-wrap items-center gap-2">
              <SectionHeading>What it could mean</SectionHeading>
              <span class="rounded-full bg-indigo-100 dark:bg-indigo-500/20 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">AI analysis</span>
            </div>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">An interpretation built only on the figures above, not a forecast or a verdict on any candidate.</p>
            <div class="mt-4 space-y-4">
              <div v-for="(a, i) in f.analysis" :key="a.heading" class="flex gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">{{ i + 1 }}</span>
                <div>
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ a.heading }}</h3>
                  <p class="mt-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ a.text }} <Cite :keys="a.sources" :index="sourceIndex" /></p>
                </div>
              </div>
            </div>
            <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Questions worth asking the candidates</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-800 dark:text-gray-200">
              <li v-for="q in f.questions" :key="q">{{ q }}</li>
            </ul>
          </section>

          <!-- Sources -->
          <section class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-4 ring-1 ring-gray-900/5 dark:ring-white/5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">Sources</h2>
            <ol class="mt-2 space-y-1 text-sm">
              <li v-for="(key, i) in sourceOrder" :id="`source-${i + 1}`" :key="key" class="flex gap-2">
                <span class="w-6 shrink-0 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ i + 1 }}.</span>
                <span><a :href="f.sources[key].url" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ f.sources[key].label }}</a><span v-if="f.sources[key].date" class="text-gray-500 dark:text-gray-400"> · {{ formatDate(f.sources[key].date) }}</span></span>
              </li>
            </ol>
            <p v-for="g in f.gaps" :key="g" class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ g }}</p>
          </section>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ElectionsNav from '../Partials/ElectionsNav.vue'
import { formatDate } from '../Partials/format'

defineOptions({ layout: (hh, page) => hh(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

type Item = { label: string; amount: number }
type Source = { label: string; url: string; date: string | null }
type Cited = { sources: string[] }

const props = defineProps<{
  finances: {
    title: string
    subtitle: string
    read_on: string
    year: number
    sources: Record<string, Source>
    stats: ({ value: string; label: string } & Cited)[]
    revenue: { total: number; items: Item[]; note: string } & Cited
    expenses: { total: number; items: Item[]; note: string } & Cited
    tax_bill: { total: number; items: Item[]; note: string } & Cited
    balance: ({ label: string; value: string; detail: string } & Cited)[]
    budget_2026: ({ text: string } & Cited)[]
    projects: { reference: Item; items: (Item & { term: string; detail: string } & Cited)[]; note: string }
    scenarios: {
      limit_pct: number
      current: { label: string; annual: number; pct: number }
      steps: { label: string; annual: number; pct: number }[]
      payments: { label: string; annual: number; compare: string }[]
      rule_of_thumb: string
      assumptions: string
    } & Cited
    analysis: ({ heading: string; text: string } & Cited)[]
    questions: string[]
    gaps: string[]
  }
}>()

const f = computed(() => props.finances)

// Number sources in the order they first appear on the page.
const sourceOrder = computed(() => {
  const seen: string[] = []
  const add = (keys: string[]) => keys.forEach((k) => { if (!seen.includes(k)) seen.push(k) })
  const x = f.value
  x.stats.forEach((s) => add(s.sources))
  ;[x.revenue, x.expenses, x.tax_bill].forEach((s) => add(s.sources))
  x.balance.forEach((s) => add(s.sources))
  x.budget_2026.forEach((s) => add(s.sources))
  x.projects.items.forEach((s) => add(s.sources))
  add(x.scenarios.sources)
  x.analysis.forEach((s) => add(s.sources))
  Object.keys(x.sources).forEach((k) => add([k]))
  return seen
})
const sourceIndex = computed(() => Object.fromEntries(sourceOrder.value.map((k, i) => [k, i + 1])))

const money = (n: number) => (n >= 1e6 ? `$${(n / 1e6).toFixed(n >= 1e8 ? 0 : 1)}M` : `$${Math.round(n / 1000)}K`)
const pct = (n: number, total: number) => `${((n / total) * 100).toFixed(0)}%`
const barWidth = (n: number, max: number) => `${Math.max((n / max) * 100, 1)}%`

const maxExpense = computed(() => Math.max(...f.value.expenses.items.map((i) => i.amount)))
const maxProject = computed(() => Math.max(...f.value.projects.items.map((i) => i.amount)))

const scenarioRows = computed(() => [f.value.scenarios.current, ...f.value.scenarios.steps])
const scaleMax = computed(() => Math.max(35, ...scenarioRows.value.map((r) => r.pct)) + 2)

// Categorical slots in fixed order (validated light and dark; values always shown beside them).
const swatches = [
  'bg-[#2a78d6] dark:bg-[#3987e5]',
  'bg-[#eb6834] dark:bg-[#d95926]',
  'bg-[#1baf7a] dark:bg-[#199e70]',
  'bg-[#eda100] dark:bg-[#c98500]',
  'bg-[#e87ba4] dark:bg-[#d55181]',
  'bg-[#008300] dark:bg-[#008300]',
  'bg-[#4a3aa7] dark:bg-[#9085e9]',
]
const swatch = (i: number) => swatches[i % swatches.length]

const SectionHeading = defineComponent({
  setup: (_, { slots }) => () =>
    h('h2', { class: 'flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300' }, [
      h('span', { class: 'h-4 w-1 rounded-full bg-emerald-500', 'aria-hidden': 'true' }),
      slots.default?.(),
    ]),
})

/** Superscript source numbers, each jumping to the Sources list. */
const Cite = defineComponent({
  props: { keys: { type: Array as () => string[], required: true }, index: { type: Object as () => Record<string, number>, required: true } },
  setup: (p) => () =>
    h('sup', { class: 'whitespace-nowrap text-[10px] font-medium' }, p.keys.map((k, i) => [
      i ? h('span', { class: 'text-gray-400' }, ',') : null,
      h('a', { href: `#source-${p.index[k]}`, class: 'text-indigo-500 hover:underline dark:text-indigo-400', title: 'See source' }, `[${p.index[k]}]`),
    ])),
})

/** A 100% stacked bar with 2px gaps between segments and a tooltip on each. */
const StackedBar = defineComponent({
  props: { items: { type: Array as () => Item[], required: true }, total: { type: Number, required: true }, labels: { type: Boolean, default: false } },
  setup: (p) => () =>
    h('div', {}, [
      h('div', { class: 'flex h-8 w-full gap-0.5 overflow-hidden rounded' },
        p.items.map((item, i) => h('div', {
          class: ['h-full first:rounded-l last:rounded-r', swatch(i)],
          style: { width: `${(item.amount / p.total) * 100}%` },
          title: `${item.label}: ${money(item.amount)} (${pct(item.amount, p.total)})`,
        }))),
      p.labels
        ? h('div', { class: 'mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm' }, p.items.map((item, i) => h('span', { class: 'flex items-center gap-1.5' }, [
            h('span', { class: ['h-3 w-3 rounded-sm', swatch(i)], 'aria-hidden': 'true' }),
            h('span', { class: 'text-gray-800 dark:text-gray-200' }, item.label),
            h('span', { class: 'font-medium tabular-nums text-gray-900 dark:text-white' }, `${money(item.amount)} · ${pct(item.amount, p.total)}`),
          ])))
        : null,
    ]),
})
</script>
