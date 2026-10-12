<template>
  <!-- Two columns from large screens up: the record (two-thirds) and, beside it, the totals by year (one third), its
       top level with the first bill card (the lg:mt below is the first year's padding plus its heading and gap).
       On phones the totals come first, as a compact index into the years. -->
  <div class="grid grid-cols-1 gap-x-8 lg:grid-cols-3 lg:items-start">
    <aside class="order-1 mb-4 lg:order-none lg:sticky lg:top-24 lg:col-start-3 lg:row-start-2 lg:mb-0 lg:mt-[3.75rem]" aria-label="Voting record by year">
      <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Voting record by year</h3>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
          <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ voteTotal }}</span>
          recorded vote{{ voteTotal === 1 ? '' : 's' }}<template v-if="billTotal"> on {{ billTotal }} bill{{ billTotal === 1 ? '' : 's' }}</template>
        </p>
        <div v-if="voteTotal" class="mt-2">
          <div class="flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" role="img" :aria-label="`${countOf('yea')} yea, ${countOf('nay')} nay`">
            <span class="bg-emerald-500" :style="{ width: pct(countOf('yea'), voteTotal) }" />
            <span class="bg-rose-500" :style="{ width: pct(countOf('nay'), voteTotal) }" />
          </div>
          <p class="mt-1 flex justify-between text-xs">
            <span class="text-emerald-700 dark:text-emerald-400">{{ countOf('yea') }} yea</span>
            <span class="text-rose-700 dark:text-rose-400">{{ countOf('nay') }} nay</span>
          </p>
        </div>

        <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-700/60">
          <li v-for="y in yearTotals" :key="y.label">
            <component
              :is="y.votes || y.others ? 'button' : 'div'"
              type="button"
              class="block w-full py-2.5 text-left"
              :class="y.votes || y.others ? 'rounded hover:bg-gray-50 dark:hover:bg-gray-700/40' : ''"
              @click="jumpToYear(y.label)"
            >
              <span class="flex items-baseline justify-between gap-2">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ y.label }}</span>
                <span class="text-xs text-gray-600 dark:text-gray-300">{{ y.votes }} vote{{ y.votes === 1 ? '' : 's' }}</span>
              </span>
              <span v-if="y.votes" class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <span class="bg-emerald-500" :style="{ width: pct(y.yea, y.votes) }" />
                <span class="bg-rose-500" :style="{ width: pct(y.nay, y.votes) }" />
              </span>
              <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                <template v-if="y.votes">{{ y.yea }} yea · {{ y.nay }} nay<template v-if="y.abstained"> · {{ y.abstained }} abstained</template> · {{ y.bills }} bill{{ y.bills === 1 ? '' : 's' }}<template v-if="y.motions"> · {{ y.motions }} motion{{ y.motions === 1 ? '' : 's' }}</template></template>
                <template v-else>No recorded votes</template>
                <template v-if="y.others"> · {{ y.others }} other item{{ y.others === 1 ? '' : 's' }}</template>
              </span>
            </component>
          </li>
        </ul>
      </section>

      <!-- What they introduced themselves: one sponsor per bill in BC, no co-sponsors. Shown for a member with a recorded vote history. -->
      <section v-if="sponsored?.length || voteTotal" class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bills and motions they introduced</h3>
        <p v-if="!sponsored?.length" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
          None on record: no bill or motion introduced by them in this Parliament.
        </p>
        <ul v-else class="mt-2 divide-y divide-gray-100 dark:divide-gray-700/60">
          <li v-for="s in sponsored" :key="s.id" class="py-3 first:pt-1">
            <p class="text-sm font-semibold leading-snug text-gray-900 dark:text-white">
              <a v-if="isHttpUrl(s.source_url)" :href="s.source_url" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ s.title }}</a>
              <template v-else>{{ s.title }}</template>
            </p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
              {{ s.reference }} · {{ s.session }}<template v-if="s.introduced_on"> · introduced {{ formatDate(s.introduced_on) }}</template>
            </p>
            <p class="mt-1.5">
              <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ options.sponsorStatuses?.[s.status] ?? s.status }}</span>
              <span v-if="s.status_note" class="ml-1.5 text-xs text-gray-500 dark:text-gray-400">{{ s.status_note }}</span>
            </p>
            <p v-if="s.summary" class="mt-1.5 text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ s.summary }}</p>
            <p v-if="s.context" class="mt-1.5 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">{{ s.context }}</p>
          </li>
        </ul>
      </section>
    </aside>

    <!-- Row one: the totals line and the filter pills, across both columns. -->
    <div class="order-2 min-w-0 lg:order-none lg:col-span-3 lg:col-start-1 lg:row-start-1">
    <!-- Totals and filters. -->
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-4">
      <p class="text-sm text-gray-600 dark:text-gray-300">
        <span class="font-semibold text-gray-900 dark:text-white">{{ voteTotal }}</span> recorded vote{{ voteTotal === 1 ? '' : 's' }}
        <template v-if="billTotal"> on {{ billTotal }} bill{{ billTotal === 1 ? '' : 's' }}<template v-if="motionTotal"> and {{ motionTotal }} other motion{{ motionTotal === 1 ? '' : 's' }}</template></template>
        <template v-if="entryTotal"> · {{ entryTotal }} other item{{ entryTotal === 1 ? '' : 's' }}</template>
        <template v-if="voteTotal"> · <span class="text-emerald-700 dark:text-emerald-400">{{ countOf('yea') }} yea</span>, <span class="text-rose-700 dark:text-rose-400">{{ countOf('nay') }} nay</span><template v-if="countOf('abstained')">, {{ countOf('abstained') }} abstained</template></template>
      </p>
      <div v-if="voteTotal" class="flex gap-1.5" role="group" aria-label="Filter by how they voted">
        <button
          v-for="f in voteFilters"
          :key="f.key"
          type="button"
          :aria-pressed="voteFilter === f.key"
          :class="voteFilter === f.key ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
          class="tap-target-touch rounded-full px-3 py-1 text-sm font-medium"
          @click="voteFilter = f.key"
        >{{ f.label }}</button>
      </div>
    </div>

    <!-- Subjects the votes and items are tagged with. -->
    <div v-if="subjects.length > 1" class="-mx-4 mb-2 flex gap-1.5 overflow-x-auto px-4 py-1 scrollbar-hide sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Filter by subject">
      <button
        v-for="s in subjects"
        :key="s.slug"
        type="button"
        :aria-pressed="tagFilter === s.slug"
        :class="tagFilter === s.slug ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600'"
        class="tap-target-touch shrink-0 rounded-full px-3 py-1 text-sm font-medium"
        @click="tagFilter = tagFilter === s.slug ? null : s.slug"
      >{{ s.name }} <span class="opacity-70">{{ s.count }}</span></button>
    </div>

    </div>

    <!-- Row two: the record itself, with the totals panel level with its first year. -->
    <div class="order-3 min-w-0 lg:order-none lg:col-span-2 lg:col-start-1 lg:row-start-2">
    <p v-if="filteredYears.length === 0" class="py-6 text-sm text-gray-500 dark:text-gray-400">Nothing matches these filters.</p>

    <!-- One section per year, newest first. -->
    <section v-for="year in visibleYears" :id="`record-year-${year.label}`" :key="year.label" class="scroll-mt-28 border-t border-gray-200 py-5 first:border-t-0 dark:border-gray-700">
      <h3 class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <span class="text-xl font-semibold text-gray-900 dark:text-white">{{ year.label }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">
          {{ year.votes }} vote{{ year.votes === 1 ? '' : 's' }}<template v-if="year.bills"> on {{ year.bills }} bill{{ year.bills === 1 ? '' : 's' }}</template><template v-if="year.others"> · {{ year.others }} other item{{ year.others === 1 ? '' : 's' }}</template>
        </span>
      </h3>

      <ul class="mt-3 space-y-3">
        <li v-for="item in year.items" :key="`${item.type}-${item.id}`">
          <!-- A bill: one entry, its own stage votes up front, amendments and committee votes folded under it. -->
          <article v-if="item.type === 'bill'" class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm font-semibold leading-snug text-gray-900 dark:text-white">
              {{ item.bill_title }}
              <span v-if="isNew(item)" :class="newPillClass" class="ml-1 align-middle">New</span>
            </p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
              {{ item.stage_votes.length + item.other_votes.length }} recorded vote{{ item.stage_votes.length + item.other_votes.length === 1 ? '' : 's' }}
              <template v-if="item.first_on && item.last_on && item.first_on !== item.last_on"> · {{ formatDate(item.first_on) }} to {{ formatDate(item.last_on) }}</template>
            </p>

            <ul v-if="item.stage_votes.length" class="mt-2 divide-y divide-gray-100 dark:divide-gray-700/60">
              <li v-for="v in item.stage_votes" :key="v.id" class="flex items-start gap-3 py-2">
                <span :class="voteClass(v.vote)" class="mt-0.5 inline-flex h-6 w-14 shrink-0 items-center justify-center rounded text-[11px] font-bold uppercase tracking-wide">{{ options.voteLabels?.[v.vote] ?? v.vote }}</span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ options.voteStages?.[v.stage ?? ''] ?? v.title }}</p>
                  <p class="flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                    <time :datetime="v.voted_on ?? undefined">{{ formatDate(v.voted_on) }}</time>
                    <span v-if="v.outcome" :class="v.outcome === 'passed' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" class="font-medium">· {{ options.voteOutcomes?.[v.outcome] ?? v.outcome }}</span>
                    <span v-if="v.tally_for !== null && v.tally_against !== null">· {{ v.tally_for }}–{{ v.tally_against }}</span>
                    <a v-if="isHttpUrl(v.source_url)" :href="v.source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">· {{ v.source_name ?? hostOf(v.source_url) }}</a>
                    <button v-if="editable" type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete-vote', v)">Delete</button>
                  </p>
                </div>
              </li>
            </ul>
            <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400">No reading-stage division recorded for this member; the votes below are on amendments and committee stages.</p>

            <!-- The two synopses sit after the votes and start collapsed. -->
            <div v-if="item.note && (item.note.purpose || item.note.vote_analysis)" class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700/60">
              <button
                type="button"
                class="tap-target-touch inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                :aria-expanded="openNotes.includes(item.bill_key)"
                @click="toggleNote(item.bill_key)"
              >
                <svg :class="openNotes.includes(item.bill_key) ? 'rotate-90' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                What the bill does, and why they voted this way
              </button>
              <div v-if="openNotes.includes(item.bill_key)" class="mt-2 space-y-3 rounded-md bg-indigo-50/60 p-3 dark:bg-indigo-500/10">
                <div v-if="item.note.purpose">
                  <h4 class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">What the bill does</h4>
                  <p class="mt-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ item.note.purpose }}</p>
                </div>
                <div v-if="item.note.vote_analysis">
                  <h4 class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Why they voted this way, and the result</h4>
                  <p class="mt-0.5 text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ item.note.vote_analysis }}</p>
                </div>
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                  <span class="rounded bg-white px-1.5 py-0.5 font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600">AI-written from the public record</span>
                  <span v-if="item.note.basis">Based on: {{ options.noteBases?.[item.note.basis] ?? item.note.basis }}.</span>
                  <template v-for="(s, i) in item.note.sources" :key="s.url">
                    <a v-if="isHttpUrl(s.url)" :href="s.url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">{{ s.label }}</a>
                    <span v-if="i < item.note.sources.length - 1" aria-hidden="true">·</span>
                  </template>
                </p>
              </div>
            </div>

            <!-- Amendments, committee clause votes and bill-specific procedural votes. -->
            <div v-if="item.other_votes.length" class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700/60">
              <button
                type="button"
                class="tap-target-touch inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                :aria-expanded="openBills.includes(item.bill_key)"
                @click="toggleBill(item.bill_key)"
              >
                <svg :class="openBills.includes(item.bill_key) ? 'rotate-90' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                Amendments and committee votes ({{ item.other_votes.length }})
              </button>
              <ul v-if="openBills.includes(item.bill_key)" class="mt-2 space-y-2">
                <li v-for="v in item.other_votes" :key="v.id" class="flex items-start gap-3 rounded-md bg-gray-50 p-2 dark:bg-gray-900/40">
                  <span :class="voteClass(v.vote)" class="mt-0.5 inline-flex h-6 w-14 shrink-0 items-center justify-center rounded text-[11px] font-bold uppercase tracking-wide">{{ options.voteLabels?.[v.vote] ?? v.vote }}</span>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium leading-snug text-gray-900 dark:text-white">{{ detail(v) }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500 dark:text-gray-400">
                      <time :datetime="v.voted_on ?? undefined">{{ formatDate(v.voted_on) }}</time>
                      <span v-if="v.outcome" :class="v.outcome === 'passed' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" class="font-medium">· {{ options.voteOutcomes?.[v.outcome] ?? v.outcome }}</span>
                      <span v-if="v.tally_for !== null && v.tally_against !== null">· {{ v.tally_for }}–{{ v.tally_against }}</span>
                      <a v-if="isHttpUrl(v.source_url)" :href="v.source_url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">· {{ v.source_name ?? hostOf(v.source_url) }}</a>
                      <button v-if="editable" type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete-vote', v)">Delete</button>
                    </p>
                  </div>
                </li>
              </ul>
            </div>

            <div v-if="item.tags.length" class="mt-2 flex flex-wrap gap-1.5">
              <button
                v-for="t in item.tags"
                :key="t.slug"
                type="button"
                class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300"
                @click="tagFilter = t.slug"
              >{{ t.name }}</button>
            </div>
          </article>

          <!-- A recorded vote. -->
          <article v-else-if="item.type === 'vote'" class="flex gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <span :class="voteClass(item.vote)" class="mt-0.5 inline-flex h-7 w-16 shrink-0 items-center justify-center rounded text-xs font-bold uppercase tracking-wide">{{ options.voteLabels?.[item.vote] ?? item.vote }}</span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium leading-snug text-gray-900 dark:text-white">
                {{ item.title }}
                <span v-if="isNew(item)" :class="newPillClass" class="ml-1 align-middle">New</span>
              </p>
              <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                <time :datetime="item.voted_on ?? undefined">{{ formatDate(item.voted_on) }}</time>
                <span v-if="item.reference">· {{ item.reference }}</span>
                <span v-if="item.outcome" :class="item.outcome === 'passed' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'" class="font-medium">· {{ options.voteOutcomes?.[item.outcome] ?? item.outcome }}</span>
                <span v-if="item.tally_for !== null && item.tally_against !== null">· {{ item.tally_for }}–{{ item.tally_against }}</span>
              </p>
              <p v-if="item.description" class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300" :class="openIds.includes(item.id) ? '' : 'line-clamp-2'">{{ item.description }}</p>
              <div class="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px]">
                <span class="flex min-w-0 flex-wrap items-center gap-1.5">
                  <button
                    v-for="t in item.tags"
                    :key="t.slug"
                    type="button"
                    class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300"
                    @click="tagFilter = t.slug"
                  >{{ t.name }}</button>
                </span>
                <span class="flex shrink-0 items-center gap-3">
                  <button v-if="(item.description?.length ?? 0) > 130" type="button" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400" :aria-expanded="openIds.includes(item.id)" @click="toggle(item.id)">{{ openIds.includes(item.id) ? 'Less' : 'More' }}</button>
                  <a v-if="isHttpUrl(item.source_url)" :href="item.source_url" target="_blank" rel="noopener noreferrer" class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">{{ item.source_name ?? hostOf(item.source_url) }}</a>
                  <button v-if="editable" type="button" class="tap-target-touch text-red-600 hover:text-red-800 dark:text-red-400" @click="$emit('delete-vote', item)">Delete</button>
                </span>
              </div>
            </div>
          </article>

          <!-- The narrative items: what they did in office, with its source. -->
          <div v-else class="rounded-lg border border-dashed border-gray-300 p-3 dark:border-gray-600">
            <EntryCard :entry="item" :options="options" :editable="editable" :is-new="isNew(item)" @delete="$emit('delete-entry', $event)" />
          </div>
        </li>
      </ul>

    </section>

    <!-- Infinite scroll: reaching this line brings the next batch in, across years. -->
    <div v-if="shown < totalItems" ref="sentinel" class="flex items-center justify-center gap-2 py-6 text-xs text-gray-500 dark:text-gray-400" role="status">
      <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
      Loading more of {{ totalItems }}
      <button type="button" class="ml-2 font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="more">Show now</button>
    </div>
    <p v-else-if="totalItems > FIRST" class="py-6 text-center text-xs text-gray-400 dark:text-gray-500">That's all {{ totalItems }}.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useIntersectionObserver } from '@vueuse/core'
import EntryCard from '../Partials/EntryCard.vue'
import { newPillClass } from '../Partials/classes'
import { formatDate, hostOf, isHttpUrl } from '../Partials/format'
import type { Entry, Options, RecordItem, RecordYear, Sponsored, Vote } from '../Partials/types'

const props = defineProps<{
  years: RecordYear[]
  /** Bills and motions the candidate introduced themselves. */
  sponsored?: Sponsored[]
  options: Options
  editable?: boolean
  isNew: (item: Entry | Vote) => boolean
}>()
defineEmits<{ 'delete-vote': [vote: Vote]; 'delete-entry': [entry: Entry] }>()

// Infinite scroll: the first FIRST items, then STEP more each time the bottom comes into view.
const FIRST = 30
const STEP = 30

const voteFilter = ref<'all' | 'yea' | 'nay' | 'abstained'>('all')
const tagFilter = ref<string | null>(null)
const shown = ref(FIRST)
const sentinel = ref<HTMLElement | null>(null)
const openIds = ref<number[]>([])
const toggle = (id: number) => {
  openIds.value = openIds.value.includes(id) ? openIds.value.filter((x) => x !== id) : [...openIds.value, id]
}

const everything = computed<RecordItem[]>(() => props.years.flatMap((y) => y.items))
/** Every recorded vote, whether inside a bill or standing alone. */
const allVotes = (item: RecordItem): Vote[] => (item.type === 'bill' ? [...item.stage_votes, ...item.other_votes] : item.type === 'vote' ? [item] : [])
const votes = computed(() => everything.value.flatMap(allVotes))
const voteTotal = computed(() => votes.value.length)
/** A bill, as opposed to a motion (which is grouped the same way, with its amendments folded under it). */
const isBill = (i: RecordItem) => i.type === 'bill' && i.kind !== 'motion'
const isMotion = (i: RecordItem) => i.type === 'vote' || (i.type === 'bill' && i.kind === 'motion')
const billTotal = computed(() => everything.value.filter(isBill).length)
const motionTotal = computed(() => everything.value.filter(isMotion).length)
const entryTotal = computed(() => everything.value.filter((i) => i.type === 'entry').length)
const countOf = (how: string) => votes.value.filter((v) => v.vote === how).length

const openBills = ref<string[]>([])
const openNotes = ref<string[]>([])
const toggleNote = (key: string) => {
  openNotes.value = openNotes.value.includes(key) ? openNotes.value.filter((k) => k !== key) : [...openNotes.value, key]
}
const toggleBill = (key: string) => {
  openBills.value = openBills.value.includes(key) ? openBills.value.filter((k) => k !== key) : [...openBills.value, key]
}
/** An amendment or committee vote, without repeating the bill's name it sits under. */
const detail = (v: Vote) => v.title.replace(/^Bill M?\d+(?:: [^—]+?)? — /, '').replace(/^./, (c) => c.toUpperCase())

const pct = (n: number, of: number) => (of > 0 ? `${((n / of) * 100).toFixed(1)}%` : '0%')

/** The whole record by year (never narrowed by the filters): votes, how they went, bills and motions. */
const yearTotals = computed(() =>
  props.years.map((y) => {
    const vs = y.items.flatMap(allVotes)
    return {
      label: y.year === null ? 'Undated' : String(y.year),
      votes: vs.length,
      yea: vs.filter((v) => v.vote === 'yea').length,
      nay: vs.filter((v) => v.vote === 'nay').length,
      abstained: vs.filter((v) => v.vote === 'abstained').length,
      bills: y.items.filter(isBill).length,
      motions: y.items.filter(isMotion).length,
      others: y.items.filter((i) => i.type === 'entry').length,
    }
  }),
)

/** Scroll to a year, first letting the infinite scroll reach it, and clear filters hiding it. */
const jumpToYear = (label: string) => {
  voteFilter.value = 'all'
  tagFilter.value = null
  let needed = 0
  for (const y of props.years) {
    needed += y.items.length
    if ((y.year === null ? 'Undated' : String(y.year)) === label) break
  }
  shown.value = Math.max(shown.value, needed)
  nextTick(() => nextTick(() => document.getElementById(`record-year-${label}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })))
}

const voteFilters = computed(() => [
  { key: 'all' as const, label: 'All' },
  { key: 'yea' as const, label: 'Yea' },
  { key: 'nay' as const, label: 'Nay' },
  ...(countOf('abstained') ? [{ key: 'abstained' as const, label: 'Abstained' }] : []),
])

/** Subject tags on anything in the record, most used first. */
const subjects = computed(() => {
  const found = new Map<string, { slug: string; name: string; count: number }>()
  for (const item of everything.value) {
    for (const tag of item.tags ?? []) {
      if (tag.kind === 'news') continue
      const row = found.get(tag.slug) ?? { slug: tag.slug, name: tag.name, count: 0 }
      row.count++
      found.set(tag.slug, row)
    }
  }
  return [...found.values()].sort((a, b) => b.count - a.count || a.name.localeCompare(b.name))
})

type YearView = { label: string; votes: number; bills: number; others: number; items: RecordItem[] }
/** An item with the filters applied: a bill keeps only the votes that match (and drops if none do). */
const applyFilters = (i: RecordItem): RecordItem | null => {
  if (tagFilter.value !== null && !(i.tags ?? []).some((t) => t.slug === tagFilter.value)) return null
  if (voteFilter.value === 'all') return i
  if (i.type === 'entry') return null
  if (i.type === 'vote') return i.vote === voteFilter.value ? i : null
  const stage = i.stage_votes.filter((v) => v.vote === voteFilter.value)
  const other = i.other_votes.filter((v) => v.vote === voteFilter.value)
  return stage.length + other.length ? { ...i, stage_votes: stage, other_votes: other } : null
}
const filteredYears = computed<YearView[]>(() =>
  props.years
    .map((y) => {
      const items = y.items.map(applyFilters).filter((i): i is RecordItem => i !== null)
      return {
        label: y.year === null ? 'Undated' : String(y.year),
        votes: items.reduce((n, i) => n + allVotes(i).length, 0),
        bills: items.filter(isBill).length,
        others: items.filter((i) => i.type === 'entry').length + items.filter(isMotion).length,
        items,
      }
    })
    .filter((y) => y.items.length > 0),
)

const totalItems = computed(() => filteredYears.value.reduce((n, y) => n + y.items.length, 0))
/** The years with only as many items as have been scrolled to, in order, across years. */
const visibleYears = computed(() => {
  let budget = shown.value
  const out: YearView[] = []
  for (const year of filteredYears.value) {
    if (budget <= 0) break
    const items = year.items.slice(0, budget)
    budget -= items.length
    out.push({ ...year, items })
  }
  return out
})
const more = () => {
  shown.value += STEP
}
useIntersectionObserver(sentinel, ([entry]) => {
  if (entry?.isIntersecting) more()
}, { rootMargin: '400px 0px' })
// A new filter starts the list over.
watch([voteFilter, tagFilter], () => {
  shown.value = FIRST
})

const voteClass = (how: string) =>
  (
    {
      yea: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
      nay: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
    } as Record<string, string>
  )[how] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
</script>
