<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="Elections" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">Elections</h1>
        <button
          type="button"
          class="tap-target-touch inline-flex items-center px-4 py-2 rounded-md shadow-sm bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
          :aria-expanded="inviting"
          aria-controls="elections-invite"
          @click="toggleInvite"
        >{{ inviting ? 'Close' : 'Invite someone' }}</button>
      </div>

      <form
        v-if="inviting"
        id="elections-invite"
        class="mb-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-4 sm:p-6"
        @submit.prevent="sendInvite"
      >
        <label for="elections-invite-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Their email</label>
        <p class="text-sm text-gray-500 dark:text-gray-400">They'll get an email to set a password, then can view Elections only, read-only.</p>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
          <input
            id="elections-invite-email"
            ref="emailInput"
            v-model="inviteForm.email"
            type="email"
            inputmode="email"
            autocomplete="off"
            required
            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-base sm:text-sm"
          />
          <button
            type="submit"
            :disabled="inviteForm.processing || !inviteForm.email"
            class="tap-target-touch shrink-0 px-4 py-2 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >{{ inviteForm.processing ? 'Sending…' : 'Send invitation' }}</button>
        </div>
        <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ inviteForm.errors.email }}</p>
      </form>

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
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import { computed, nextTick, onMounted, ref } from 'vue'
import { formatDate } from './Partials/format'
import { useUpdates } from './Partials/updates'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

defineProps<{
  salmonArm: { candidates: number; votingDay: string | null }
}>()

// Anyone who can open Elections may invite someone else to it (Elections only);
// the result arrives as the layout's flash message.
const inviting = ref(false)
const emailInput = ref<HTMLInputElement | null>(null)
const inviteForm = useForm({ email: '' })

const toggleInvite = async () => {
  inviting.value = !inviting.value
  if (inviting.value) {
    await nextTick()
    emailInput.value?.focus()
  }
}

const sendInvite = () => {
  inviteForm.post(route('admin.elections.invitations.store'), {
    preserveScroll: true,
    // Refusals arrive as an error on the email field, so this runs only on a real send.
    onSuccess: () => {
      inviteForm.reset()
      inviting.value = false
    },
  })
}

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
  const finances = updates.financesUnread()

  const where = [
    candidates.items ? `${candidates.items} on ${candidates.candidates} candidate${candidates.candidates === 1 ? '' : 's'}` : '',
    events ? 'election events' : '',
    pulse ? 'Community Pulse' : '',
    subjects ? 'Browse by subject' : '',
    plans ? `${plans} City plan${plans === 1 ? '' : 's'}` : '',
    finances ? 'City finances' : '',
  ].filter(Boolean)

  return {
    // Subjects counts distinct arrival times, not records, so it adds 1 however much was tagged.
    total: candidates.items + events + pulse + (subjects ? 1 : 0) + plans + (finances ? 1 : 0),
    where: where.join(' · '),
  }
})
</script>
