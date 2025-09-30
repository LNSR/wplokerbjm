<template>
  <Header :logo="props.logo" />
  <main class="container mx-auto max-w-[95vmax] lg:max-w-[90vmax] px-4 py-8">
    <SearchForm v-if="props.hero" :current-search="props.hero.currentSearch" :current-lokasi="props.hero.currentLokasi"
      :current-gender="props.hero.currentGender" :current-pendidikan="props.hero.currentPendidikan"
      :current-sort="props.hero.currentSort" :archive-link="props.hero.archiveLink" />
    <JobCarousel v-if="props.carousel" :jobs="props.carousel.jobs" :total-jobs="props.carousel.totalJobs" />
    <JobGrid v-if="props.jobGrid" :jobs="props.jobGrid.jobs" :max-num-pages="props.jobGrid.maxNumPages"
      :context="props.jobGrid.context" :filters="props.jobGrid.filters" :title="props.jobGrid.title"
      :total-jobs="props.jobGrid.totalJobs" />
    <FloatingActionButton />
  </main>
</template>
<script setup lang="ts">
import Header from '@/layouts/Header.vue';
import SearchForm from '@/components/Homepage/SearchForm.vue';
import FloatingActionButton from '@/components/Shared/FloatingActionButton.vue';
import JobGrid from '@/components/Homepage/JobGrid.vue';
import JobCarousel from '@/components/Homepage/JobCarousel.vue';
import type { SearchFormProps, JobGridProps, CarouselProps, LayoutProps } from '@/types'

const props = defineProps<{
  hero: SearchFormProps,
  carousel: CarouselProps,
  jobGrid: JobGridProps,
  logo: LayoutProps['logo']
}>();
</script>