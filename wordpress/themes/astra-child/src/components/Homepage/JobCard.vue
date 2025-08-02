<template>
  <article :class="cardClass" @click="handleClick" style="cursor:pointer">
    <a :href="permalink" class="contents">
      <div :class="bodyClass">
        <div class="flex-1 flex flex-col justify-start">
          <div class="flex items-center justify-between mb-2 gap-x-2">
            <h3 class="card-title text-lg md:text-xl !font-bold group-hover:text-blue-700 transition-colors">
              {{ jobdata['title'] }}
            </h3>
            <time class="text-lg !font-semibold text-center gap-2" :datetime="jobdata['post_time']">
              {{ timeAgo }}
            </time>
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
              <span v-if="row.label !== 'Deadline'" class="flex items-center text-base md:text-base font-semibold gap-2 py-1">
                <i :class="['fas', row.icon, 'text-[var(--ast-global-color-1)]']"></i>
                <span v-html="row.value"></span>
              </span>
            </template>
          </div>
        </div>
        <div v-if="hasStatusOrDeadline" class="divider my-2"></div>
        <div class="flex items-center justify-between font-semibold">
          <span v-if="jobdata['statusjob']" v-html="jobdata['statusjob']"></span>
          <span v-if="jobdata['deadline']" v-html="jobdata['deadline']"></span>
        </div>
      </div>
    </a>
  </article>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import type { PropType } from 'vue'
import { useTimeAgo } from '@/composables/useTime'
import { useJobCard } from '@/composables/useJobCard'

const props = defineProps({
  jobdata: { type: Object, required: true },
  variant: { type: String as PropType<'featured' | 'carousel'>, required: true },
  permalink: { type: String, required: true },
  selected: { type: Boolean, default: false },
  onClick: { type: Function }
})

const emit = defineEmits(['click'])

function handleClick(event: MouseEvent) {
  const isTabletOrDesktop = window.matchMedia('(min-width: 768px)').matches
  if (!isTabletOrDesktop) return

  if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return

  event.preventDefault()

  if (props.variant === 'carousel' && props.onClick) {
    props.onClick(props.jobdata['slug'], event, 0)
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

const { cardClass, bodyClass } = useJobCard(props.variant, props.selected)

const summaryRows = computed(() => props.jobdata['summary_rows'] || [])
const hasStatusOrDeadline = computed(() => !!props.jobdata['statusjob'] || !!props.jobdata['deadline'])

const { timeAgo } = useTimeAgo(props.jobdata['post_time'])
</script>