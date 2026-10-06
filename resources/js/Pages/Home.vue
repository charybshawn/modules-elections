<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Elections" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white mb-6">Elections</h1>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
          :href="route('admin.elections.index')"
          class="block rounded-lg shadow-sm p-6 transition hover:ring-2"
          :class="unread.total > 0 ? 'bg-orange-50 ring-2 ring-orange-400 hover:ring-orange-500 dark:bg-orange-500/10 dark:ring-orange-500/70' : 'bg-white dark:bg-gray-800 hover:ring-gray-300 dark:hover:ring-gray-600'"
        >
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Salmon Arm Elections</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Municipal: mayor, council and School District 83 trustees.</p>
          <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            {{ salmonArm.candidates }} candidate{{ salmonArm.candidates === 1 ? '' : 's' }}<template v-if="salmonArm.votingDay"> · voting day {{ formatDate(salmonArm.votingDay) }}</template>
          </p>
          <p v-if="unread.total > 0" class="mt-3">
            <span class="inline-block rounded-full bg-orange-500 px-3 py-1 text-sm font-semibold text-white dark:bg-orange-600">{{ unread.total }} unread update{{ unread.total === 1 ? '' : 's' }}</span>
          </p>
          <p v-if="unread.total > 0" class="mt-2 text-sm text-orange-800 dark:text-orange-200">{{ unread.where }}</p>
        </Link>

        <Link
          :href="route('admin.elections.provincial')"
          class="block rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-600 transition"
        >
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">BC Provincial Election</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Salmon Arm-Shuswap riding.</p>
          <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Coming soon</p>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { computed, onMounted } from 'vue'
import { formatDate } from './Partials/format'
import { useUpdates } from './Partials/updates'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

defineProps<{
  salmonArm: { candidates: number; votingDay: string | null }
}>()

// Everything unread for this viewer, counted by the same store as every other
// badge (see Partials/updates.ts), so the numbers always agree.
const updates = useUpdates()
onMounted(() => updates.ensure())

const unread = computed(() => {
  const candidates = updates.candidatesUnread()
  const events = updates.sectionUnread('events')
  const pulse = updates.sectionUnread('pulse')
  const subjects = updates.sectionUnread('subjects')
  const plans = updates.plansUnread()

  const where = [
    candidates.items ? `${candidates.items} on ${candidates.candidates} candidate${candidates.candidates === 1 ? '' : 's'}` : '',
    events ? 'election events' : '',
    pulse ? 'Community Pulse' : '',
    subjects ? 'Browse by subject' : '',
    plans ? `${plans} City plan${plans === 1 ? '' : 's'}` : '',
  ].filter(Boolean)

  return {
    // Subjects counts distinct arrival times, not records, so it adds 1 however much was tagged.
    total: candidates.items + events + pulse + (subjects ? 1 : 0) + plans,
    where: where.join(' · '),
  }
})
</script>
