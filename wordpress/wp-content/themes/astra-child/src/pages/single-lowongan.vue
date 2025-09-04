<template>
  <Header :logo="props.logo" />
  <main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
    <JobDetail :job="props.job" />
    <FloatingActionButton />
  </main>
  <Footer />
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
import Header from '@/layouts/Header.vue';
import Footer from '@/layouts/Footer.vue';
const JobDetail = defineAsyncComponent(() => import('@/components/Shared/JobDetail.vue'));
const FloatingActionButton = defineAsyncComponent(() => import('@/components/Shared/FloatingActionButton.vue'));
import { type SingleOverlayResponse as SingleJob, type LayoutProps } from '@/types';

const props = defineProps<{ job: SingleJob,  logo: LayoutProps['logo'] }>();
</script>
