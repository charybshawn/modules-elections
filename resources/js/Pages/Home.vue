<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Elections" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white mb-6">Elections</h1>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
          :href="route('admin.elections.index')"
          class="block rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-600 transition"
        >
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Salmon Arm Elections</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Municipal: mayor, council and School District 83 trustees.</p>
          <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            {{ salmonArm.candidates }} candidate{{ salmonArm.candidates === 1 ? '' : 's' }}<template v-if="salmonArm.votingDay"> · voting day {{ formatDate(salmonArm.votingDay) }}</template>
          </p>
          <p v-if="unread.updates" class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">
            {{ unread.updates }} unread update{{ unread.updates === 1 ? '' : 's' }} across {{ unread.candidates }} candidate{{ unread.candidates === 1 ? '' : 's' }}
          </p>
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
import { computed } from 'vue'
import { formatDate } from './Partials/format'
import { toUnix, useSeen } from './Partials/seen'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

const props = defineProps<{
  salmonArm: {
    candidates: number
    votingDay: string | null
    unread: {
      candidates: { id: number; slug: string; updated_at: string | null }[]
      activity: Record<number, number[]>
      now: number
    }
  }
}>()

// Same rule as the dashboard's "new since your last visit": items added since
// the viewer last opened each candidate, plus a profile-only change counting
// as one update. Read from the viewer's seen-cookie once mounted.
const seen = useSeen(() => props.salmonArm.unread.now)
const unread = computed(() => {
  let updates = 0
  let candidates = 0
  for (const c of props.salmonArm.unread.candidates) {
    const since = seen.seenAt(c.slug)
    if (since === null) continue
    const items = (props.salmonArm.unread.activity[c.id] ?? []).filter((t) => t > since).length
    const n = items > 0 ? items : (toUnix(c.updated_at) ?? 0) > since ? 1 : 0
    if (n > 0) {
      updates += n
      candidates++
    }
  }
  return { updates, candidates }
})
</script>
