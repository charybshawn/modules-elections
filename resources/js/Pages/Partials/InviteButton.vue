<template>
  <div class="relative" @keydown.esc="open = false">
    <button
      type="button"
      class="tap-target-touch inline-flex items-center px-4 py-2 rounded-md shadow-sm bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
      :aria-expanded="open"
      :aria-controls="panelId"
      @click="toggle"
    >{{ open ? 'Close' : 'Invite someone' }}</button>

    <form
      v-if="open"
      :id="panelId"
      class="absolute left-0 sm:left-auto sm:right-0 z-30 mt-2 w-[min(24rem,calc(100vw-2rem))] rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-gray-900/10 dark:ring-white/10 p-4"
      @submit.prevent="send"
    >
      <label :for="`${panelId}-email`" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Their email</label>
      <p class="text-sm text-gray-500 dark:text-gray-400">They'll get an email to set a password, then can view Elections only, read-only.</p>
      <input
        :id="`${panelId}-email`"
        ref="emailInput"
        v-model="form.email"
        type="email"
        inputmode="email"
        autocomplete="off"
        required
        class="mt-2 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-base sm:text-sm"
      />
      <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
      <button
        type="submit"
        :disabled="form.processing || !form.email"
        class="tap-target-touch mt-3 w-full px-4 py-2 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
      >{{ form.processing ? 'Sending…' : 'Send invitation' }}</button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, useId } from 'vue'
import { useForm } from '@inertiajs/vue3'

// Anyone who can open Elections may invite someone else to it (Elections only,
// read-only). Success arrives as the layout's toast; a refusal comes back as an
// error on the email field, shown under the box.
const panelId = `elections-invite-${useId()}`
const open = ref(false)
const emailInput = ref<HTMLInputElement | null>(null)
const form = useForm({ email: '' })

const toggle = async () => {
  open.value = !open.value
  if (open.value) {
    await nextTick()
    emailInput.value?.focus()
  }
}

const send = () => {
  form.post(route('admin.elections.invitations.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      open.value = false
    },
  })
}
</script>
