<template>
  <main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
    <JobDetail :job="singleLowongan.job" />
    <FloatingActionButton />
  </main>
</template>

<script setup lang="ts">
import JobDetail from '@/components/JobDetail.vue'
import FloatingActionButton from '@/components/FloatingActionButton.vue';
import type { SingleOverlayResponse as SingleJob } from '@/types';

const singleLowongan = defineProps<{
  job: SingleJob
}>();
</script>
