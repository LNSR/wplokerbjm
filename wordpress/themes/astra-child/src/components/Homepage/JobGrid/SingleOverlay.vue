<template>
  <div v-if="visible" class="min-h-screen flex flex-col pointer-events-auto ml-7">
    <!-- Overlay background (only in JobGrid area) -->
    <div class="absolute top-0 left-0 right-0 bottom-0" @click="close"></div>
    <!-- Drawer -->
    <aside v-if="visible"
      class="relative bg-[var(--ast-global-color-4)] shadow-xl transition-transform duration-300 rounded-xl border-2 border-blue-400 w-full max-h-screen overflow-y-auto flex flex-col z-50"
      :class="drawerOpenClass">
      <button class="absolute top-5 right-4" @click="close" aria-label="Close">
        Tutup
      </button>
      <a v-if="!loading && overlay && props.permalink" :href="props.permalink" target="_blank" rel="noopener"
        class="absolute top-5 left-4 btn btn-sm btn-outline btn-primary flex items-center gap-1">
        <i class="fas fa-external-link-alt"></i>
        Buka di Tab Baru
      </a>
      <a
        v-if="!loading && overlay && isLoggedIn && editPostId"
        :href="`/wp-admin/post.php?post=${editPostId}&action=edit`"
        target="_blank"
        rel="noopener"
        class="absolute top-5 left-44 btn btn-sm btn-outline btn-warning flex items-center gap-1"
      >
        <i class="fas fa-edit"></i>
        Edit
      </a>
      <div v-if="loading" class="p-4 text-center pt-16 flex-1 flex flex-col items-center justify-center">
        <span class="sr-only">Memuat...</span>
        <svg class="animate-spin h-8 w-8 text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none"
          viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Memuat Lowongan...
      </div>
      <div v-else-if="error" class="p-4 text-red-500 pt-16 flex-1">{{ error }}</div>
      <div v-else-if="overlay" class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
        <JobDetail :job="overlay" />
      </div>
    </aside>
  </div>
</template>

<script setup lang="ts">
import { useSingleOverlay } from '@/composables/useJobGrid/useSingleOverlay'
import JobDetail from '@/components/JobDetail.vue'

const props = defineProps<{
  slug?: string
  visible?: boolean
  permalink?: string
}>()
const emit = defineEmits(['close'])

const {
  data,
  loading,
  error,
  isLoggedIn,
  editPostId,
} = useSingleOverlay(props)

const overlay = data

function close() {
  emit('close')
}

const drawerOpenClass = 'transform translate-x-0'
</script>