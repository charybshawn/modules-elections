<template>
  <!-- About the party, in this order: who leads it, how long it has existed, its record in government, what it stands for. -->
  <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-800">
    <p class="font-semibold text-gray-900 dark:text-white">{{ profile.full_name ?? profile.name }}</p>
    <dl class="mt-3 space-y-3">
      <div v-if="profile.leader">
        <dt :class="labelClass">Current leader</dt>
        <dd class="text-gray-900 dark:text-white">{{ profile.leader }}<span v-if="profile.leader_note" class="block text-gray-600 dark:text-gray-300">{{ profile.leader_note }}</span></dd>
      </div>
      <div v-if="profile.founded">
        <dt :class="labelClass">How long it has existed</dt>
        <dd class="text-gray-900 dark:text-white">{{ profile.founded }}<span v-if="profile.founded_note" class="block text-gray-600 dark:text-gray-300">{{ profile.founded_note }}</span></dd>
      </div>
      <div v-if="profile.times_in_government !== null || profile.government_note">
        <dt :class="labelClass">Times in government</dt>
        <dd class="text-gray-900 dark:text-white">
          <template v-if="profile.times_in_government !== null">{{ profile.times_in_government === 0 ? 'Never' : profile.times_in_government === 1 ? 'Once' : `${profile.times_in_government} times` }}</template>
          <span v-if="profile.government_note" class="block text-gray-600 dark:text-gray-300">{{ profile.government_note }}</span>
        </dd>
      </div>
      <div v-if="profile.principles || profile.platform.length">
        <dt :class="labelClass">Principles and platform: what it stands for</dt>
        <dd class="text-gray-900 dark:text-white">
          <p v-if="profile.principles" class="leading-relaxed">{{ profile.principles }}</p>
          <ul v-if="profile.platform.length" class="mt-2 list-disc space-y-1 pl-5 text-gray-700 dark:text-gray-300">
            <li v-for="point in profile.platform" :key="point">{{ point }}</li>
          </ul>
        </dd>
      </div>
    </dl>
    <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-gray-100 pt-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
      <span v-if="profile.as_of">Checked {{ formatDate(profile.as_of) }}.</span>
      <template v-for="(s, i) in profile.sources" :key="s.url">
        <a v-if="isHttpUrl(s.url)" :href="s.url" target="_blank" rel="noopener noreferrer" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-900 dark:decoration-gray-600 dark:hover:text-white">{{ s.label }}</a>
        <span v-if="i < profile.sources.length - 1" aria-hidden="true">·</span>
      </template>
    </p>
    <Link v-if="link && profile.slug" :href="route('admin.elections.provincial.parties.show', profile.slug)" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
      The party's politics: news and fact-check record <span aria-hidden="true">→</span>
    </Link>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { formatDate, isHttpUrl } from '../Partials/format'
import type { PartyProfile } from '../Partials/types'

defineProps<{
  profile: PartyProfile
  /** Show the link to the party's own page (off on that page itself). */
  link?: boolean
}>()

const labelClass = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'
</script>
