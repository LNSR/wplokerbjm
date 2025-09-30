<template>
  <article :class="cardClass" @click="handleClick" style="cursor:pointer">
    <a :href="permalink" class="contents">
      <div :class="bodyClass">
        <div class="flex-1 flex flex-col justify-start">
          <div class="flex items-center justify-between mb-2 gap-x-2">
            <h3 class="card-title text-lg md:text-xl !font-bold group-hover:text-blue-700 transition-colors">
              {{ jobdata['title'] }}
            </h3>
            <div class="flex items-center gap-2">
              <time class="text-lg !font-semibold text-center" :datetime="jobdata['post_time']">
                {{ timeAgo }}
              </time>
            </div>
          </div>
          <div v-if="!jobdata['nama_perusahaan']" class="divider mt-0"></div>
          <template v-else>
            <h4 class="!font-bold flex items-center gap-2 !mb-6">
              <i class="fas fa-user-tie !text-[var(--ast-global-color-1)]"></i>
              {{ jobdata['nama_perusahaan'] }}
            </h4>
            <div class="divider !-mt-4"></div>
          </template>
          <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
            <template v-for="row in summaryRows" :key="row.label">
              <span v-if="row.label !== 'Deadline'"
                class="flex items-center text-base md:text-base font-semibold gap-2 py-1">
                <i :class="['fas', row.icon, 'text-[var(--ast-global-color-1)]']"></i>
                <span v-html="row.value"></span>
              </span>
            </template>
          </div>
        </div>
        <div class="divider !my-2"></div>
        <div class="flex items-center justify-between !font-semibold">
          <span v-if="statusInfo.label"
            :class="['inline-block !px-3 !py-1 text-sm font-bold rounded', statusInfo.color]">
            {{ statusInfo.label }}
          </span>
          <div class="flex items-center !gap-2 !ml-auto">
            <span v-if="deadlineInfo.text"
              :class="['flex items-center gap-2 !px-3 !py-1 rounded font-semibold !text-sm', deadlineInfo.style]">
              <i class="fas fa-calendar-alt"></i>
              <span>{{ deadlineInfo.text }}</span>
            </span>
            <BookmarkButton :job-id="jobdata.id || 0" :variant="props.variant" />
          </div>
        </div>
      </div>
    </a>
  </article>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useTimeAgo } from '@/composables/useTime'
import { useSummaryJob } from '@/composables/useSummary'
import { useStatusJob } from '@/composables/useStatusJob'
import { useDeadline } from '@/composables/useDeadline'
import { useJobOverlayStore } from '@/stores/JobOverlay'
import BookmarkButton from '@/components/Shared/BookmarkButton.vue'
import type { JobCardProps } from '@/types/Component'

const props = defineProps<JobCardProps>()

const emit = defineEmits(['click'])
const handleClick = (event: MouseEvent) => {
  const isTabletOrDesktop = window.matchMedia('(min-width: 768px)').matches

  const { ctrlKey, metaKey, shiftKey, button } = event
  if (ctrlKey || metaKey || shiftKey || button === 1) return

  event.preventDefault();

  if (!isTabletOrDesktop) {
    window.open(props.permalink, '_blank')
    return
  }

  if (props.variant === 'carousel' && props.onClick) {
    props.onClick(props.jobdata['slug'] ?? '', event, 0)
    const grid = document.getElementById('job-grid')
    if (grid) {
      grid.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
    return
  }

  if (props.variant === 'featured') {
    emit('click', event)
  }
}

const selected = computed(() => props.jobdata['slug'] === useJobOverlayStore().selectedSlug)

const cardClass = computed(() => {
  const baseClasses = {
    carousel: "block group rounded-xl transition-all duration-300 cursor-pointer carousel-card max-w-full border-2 border-blue-400 shadow-md hover:shadow-lg hover:border-blue-600 hover:border-solid",
    featured: "block group rounded-xl transition-all duration-300 cursor-pointer w-full max-w border-2 border-blue-400 shadow-lg hover:shadow-xl hover:border-blue-600 hover:scale-[1.02] hover:border-solid"
  };

  const selectedClasses = {
    carousel: " ring-6 ring-blue-600 border-blue-700",
    featured: " ring-4 ring-blue-500 border-blue-700"
  };

  return `${baseClasses[props.variant] || ""}${selected.value ? selectedClasses[props.variant] || "" : ""}`;
});

const bodyClass = computed(() => {
  const bodyClasses = {
    carousel: "card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full",
    featured: "card-body relative p-4 gap-1 flex flex-col h-full"
  };

  return bodyClasses[props.variant] || "";
});

const summaryRows = computed(() => useSummaryJob(props.jobdata['ringkasanPekerjaan']))
const statusInfo = computed(() => useStatusJob(Number(props.jobdata['statusjob'])))
const deadlineInfo = computed(() => useDeadline(props.jobdata['deadline']));



const { timeAgo } = useTimeAgo(props.jobdata['post_time'])
</script>