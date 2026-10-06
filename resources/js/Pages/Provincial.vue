<template>
  <div class="pb-24 md:pt-6 md:pb-6">
    <AdminMobileHeader title="BC Provincial Election" :href="route('admin.elections.home')" />

    <div class="px-4 sm:px-0 max-w-5xl mx-auto">
      <Link :href="route('admin.elections.home')" class="hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm mb-4">← Elections</Link>
      <h1 class="hidden md:block text-2xl font-semibold text-gray-900 dark:text-white">BC Provincial Election</h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Salmon Arm-Shuswap riding.</p>

      <p class="mt-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
        Nothing here yet. Candidate research for the provincial election will appear on this page.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })
</script>
