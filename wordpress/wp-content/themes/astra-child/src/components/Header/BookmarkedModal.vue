<template>
  <dialog ref="modalEl" class="modal modal-bottom sm:modal-middle" :class="{ 'modal-open': modelValue }">
    <div ref="modalBox"
      :class="['modal-box !max-w-3xl p-0 flex flex-col relative max-h-[80vh] w-full rounded-t-xl overflow-hidden md:static md:left-auto md:right-auto md:bottom-auto md:m-auto md:w-auto md:z-60 md:rounded-b-xl', { 'mobile-sheet': isMobile }]"
      :style="modalStyle" @pointerdown.capture="startDrag">
      <!-- Drag Handle -->
      <div v-if="isMobile" ref="dragHandle"
        class="!drag-handle !w-12 !h-2 bg-base-content/20 rounded-full !mx-auto !mt-3 !mb-2 cursor-grab active:cursor-grabbing touch-none select-none transition-colors duration-200 hover:bg-base-content/30 active:bg-base-content/40 md:bg-base-content/15 md:hover:bg-base-content/25 md:active:bg-base-content/35"
        @pointerdown.prevent="startDrag" aria-label="Drag to resize modal" role="button" tabindex="0"></div>

      <!-- Header -->
      <div ref="modalHeader"
        class="sticky top-0 z-10 border-b border-base-300 !px-6 !py-4 flex items-center justify-between">
        <h3 class="font-bold text-lg flex items-center !gap-2">
          <i class="fas fa-bookmark"></i>
          Lowongan Tersimpan
          <span v-if="!loading && savedJobs.length > 0"
            class="bg-[var(--ast-global-color-1)] text-white text-sm rounded-full px-2 py-0.1 z-10">{{ savedJobs.length
            }}</span>
        </h3>
        <div class="flex items-center !gap-0 !pt-2">
          <button v-if="!loading && savedJobs.length > 0" @click="handleDeleteAll" :disabled="loading"
            class="!btn !btn-ghost !btn-sm md:!btn-md text-error" aria-label="hapus semua" title="hapus semua">
            <i class="fas fa-trash"></i>
            Hapus Semua
          </button>
          <button v-if="!loading && savedJobs.length > 0" @click.stop="handleRefresh" :disabled="loading"
            class="!btn !btn-ghost !btn-sm md:!btn-md" aria-label="sync ke server" title="sync ke server">
            <i :class="['fas', 'fa-sync', loading ? 'animate-spin' : '']"></i>
            Sync/Refresh
          </button>
          <button @click="closeModal" class="!btn md:!btn-md !btn-sm !btn-ghost" aria-label="close modal"
            title="close modal">
            <i class="fas fa-times"></i>
            Tutup
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto max-h-full !px-6 !py-4">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center !py-12">
          <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="alert alert-error">
          <i class="fas fa-exclamation-circle shrink-0 !h-6 !w-6"></i>
          <span>{{ error }}</span>
        </div>

        <!-- Warning State -->
        <div v-else-if="warning" class="alert alert-warning">
          <i class="fas fa-exclamation-triangle shrink-0 !h-6 !w-6"></i>
          <span>{{ warning }}</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="savedJobs.length === 0 && deletedJobs.length === 0" class="text-center py-12">
          <i class="fas fa-bookmark h-16 w-16 mx-auto text-base-300 mb-4"></i>
          <p class="text-base-content/60">Belum ada lowongan yang disimpan</p>
          <p class="text-sm text-base-content/40 mt-2">Klik ikon bookmark pada lowongan untuk menyimpannya</p>
        </div>

        <!-- Saved Jobs -->
        <div v-else>
          <div v-if="savedJobs.length > 0" class="!space-y-3 !mb-6">
            <div class="flex flex-row items-center justify-between !break-words !whitespace-normal">
              <h4 class="font-semibold text-sm text-base-content/70">Tersedia ({{ savedJobs.length }})</h4>
              <div v-if="lastSyncTime > 0 && !loading" class="text-xs font-semibold flex flex-col">
                <span class="mb-1 flex items-center gap-1">Terakhir sync:</span>
                <span>{{ formattedLastSync }}</span>
              </div>
            </div>
            <div v-for="job in displayedSavedJobs" :key="job.id"
              :class="['card bg-base-200 shadow-sm hover:shadow-md transition-all duration-300', { 'opacity-0 scale-95': removingIds.has(job.id || 0) }]">
              <div class="card-body !p-4">
                <!-- Skeleton for loading job -->
                <div v-if="job.title === ''" class="animate-pulse">
                  <div class="flex items-start justify-between !gap-3">
                    <div class="flex-1 !min-w-0">
                      <div class="h-4 bg-base-300 rounded !mb-2"></div>
                      <div class="h-3 bg-base-300 rounded !mb-2 w-3/4"></div>
                      <div class="flex flex-wrap !gap-x-4 !gap-y-1 !mb-2">
                        <div class="h-3 bg-base-300 rounded w-20"></div>
                        <div class="h-3 bg-base-300 rounded w-16"></div>
                        <div class="h-3 bg-base-300 rounded w-24"></div>
                      </div>
                      <div class="!mt-2">
                        <div class="h-3 bg-base-300 rounded w-32 !mb-2"></div>
                        <div class="h-3 bg-base-300 rounded w-28"></div>
                      </div>
                    </div>
                    <div class="flex flex-col !gap-1">
                      <div class="h-8 w-8 bg-base-300 rounded"></div>
                    </div>
                  </div>
                </div>
                <!-- Full job card -->
                <div v-else class="flex items-start justify-between !gap-3">
                  <div class="flex-1 !min-w-0">
                    <h5 class="font-bold text-base truncate !mb-1">
                      <a :href="job.permalink" target="_blank" class="hover:text-primary transition-colors">
                        {{ job.title }}
                      </a>
                    </h5>
                    <p v-if="job.nama_perusahaan" class="text-sm !font-semibold text-base-content/75 !mb-2">
                      <i class="fas fa-user-tie !text-[var(--ast-global-color-1)]"></i>
                      {{ job.nama_perusahaan }}
                    </p>
                    <div class="flex flex-wrap !gap-x-4 !gap-y-1 !mb-2">
                      <template v-for="row in useSummaryJob(job.ringkasanPekerjaan)" :key="row.label">
                        <span v-if="row.label !== 'Deadline'"
                          class="flex items-center text-base md:text-base font-semibold !gap-2 !py-1">
                          <i :class="['fas', row.icon, 'text-[var(--ast-global-color-1)]']"></i>
                          <span v-html="row.value"></span>
                        </span>
                      </template>
                    </div>
                    <div class="!mt-2 text-sm text-base-content/50">
                      <span v-if="job.statusInfo.label"
                        :class="['inline-block px-3 py-1 font-bold rounded mr-2', job.statusInfo.color]">
                        {{ job.statusInfo.label }}
                      </span>
                      <span v-if="job.deadlineInfo.text"
                        :class="['inline-block px-3 py-1 mb-4 font-bold rounded', job.deadlineInfo.style]">
                        {{ job.deadlineInfo.text }}
                      </span>
                    </div>
                    <span v-if="job.timeAgo" class="font-semibold">Diposting: {{ job.timeAgo }}</span>
                  </div>
                  <div class="flex flex-col !gap-1">
                    <button @click="removeBookmark(job.id || 0)" class="!btn !btn-xs !btn-ghost text-error"
                      title="Hapus bookmark" aria-label="Remove bookmark">
                      <i class="fas fa-trash text-lg"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Deleted Jobs -->
          <div v-if="deletedJobs.length > 0" class="!space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-sm text-base-content/70">Tidak Tersedia ({{ deletedJobs.length }})</h4>
              <button @click="handleClearDeleted" class="!btn !btn-xs !btn-ghost text-error"
                aria-label="Clear all deleted jobs">
                Hapus Semua
              </button>
            </div>
            <div v-for="id in deletedJobs" :key="id" class="card bg-base-300 opacity-60">
              <div class="card-body !p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center !gap-2">
                    <i class="fas fa-exclamation-circle !h-5 !w-5 text-error"></i>
                    <span class="text-sm">Lowongan ID #{{ id }} tidak tersedia</span>
                  </div>
                  <button @click="removeBookmark(id)" class="!btn !btn-xs !btn-ghost" title="Hapus dari daftar"
                    aria-label="Remove from deleted list">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Copy Success Toast -->
          <div v-if="showCopySuccess" class="toast toast-top toast-center z-50">
            <div class="alert alert-success">
              <i class="fas fa-check-circle stroke-current shrink-0 !h-6 !w-6"></i>
              <span>Link berhasil disalin!</span>
            </div>
          </div>

          <!-- Offline Notice -->
          <div v-if="isOffline" class="alert alert-warning !mt-4">
            <i class="fas fa-exclamation-triangle stroke-current shrink-0 !h-6 !w-6"></i>
            <span>Mode offline - menampilkan data tersimpan</span>
          </div>
        </div>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop" @click="closeModal" />
  </dialog>

  <!-- Delete All Confirmation Modal -->
  <dialog ref="deleteConfirmModal" class="modal modal-bottom sm:modal-middle"
    :class="{ 'modal-open': showDeleteConfirm }">
    <div class="modal-box">
      <h3 class="font-bold text-lg flex items-center !gap-2">
        <i class="fas fa-exclamation-triangle text-error !h-6 !w-6" aria-hidden="true"></i>
        Konfirmasi Hapus Semua
      </h3>
      <p class="!py-4">
        Apakah Anda yakin ingin menghapus semua bookmark? Tindakan ini tidak dapat dibatalkan dan akan menghapus semua
        lowongan yang telah Anda simpan.
      </p>
      <div class="modal-action">
        <button @click="cancelDeleteAll" class="!btn !btn-ghost" :disabled="loading">
          Batal
        </button>
        <button @click="confirmDeleteAll" class="!btn !btn-error" :disabled="loading">
          <span v-if="loading" class="loading loading-spinner loading-sm"></span>
          Hapus Semua
        </button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop" @click="cancelDeleteAll" />
  </dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useSummaryJob } from '@/composables/useSummary'
import { useBookmarkedModal } from '@/composables/useBookmark'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits(['update:modelValue'])

const modalEl = ref<HTMLDialogElement>()
const deleteConfirmModal = ref<HTMLDialogElement>()
const modalBox = ref<HTMLElement | null>(null)
const modalHeader = ref<HTMLElement | null>(null)
const dragHandle = ref<HTMLElement | null>(null)

// Dragging state
const translateX = ref(0)
const translateY = ref(0)
const isDragging = ref(false)
let activePointerId: number | null = null
let startClientY = 0
let startHeight = 0

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false)
const mobileMq = typeof window !== 'undefined' ? window.matchMedia('(max-width: 767.98px)') : null
let mqListener: ((ev: MediaQueryListEvent) => void) | null = null

const modalStyle = computed(() => ({
  transform: `translate(${translateX.value}px, ${translateY.value}px)`,
  transition: isDragging.value ? 'none' : 'transform 180ms ease',
  touchAction: isMobile.value ? 'none' : 'auto'
}))

function clamp(n: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, n))
}

const startDrag = (e: PointerEvent): void => {
  if ((e as PointerEvent).button && (e as PointerEvent).button !== 0) return
  if (!modalBox.value || !dragHandle.value) return
  if (!dragHandle.value.contains(e.target as Node)) return
  try {
    dragHandle.value.setPointerCapture(e.pointerId)
  } catch {
    // ignore
  }
  activePointerId = e.pointerId
  isDragging.value = true
  startClientY = e.clientY
  if (isMobile.value) {
    startHeight = modalBox.value?.clientHeight || 0
  }
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  e.preventDefault()
}

const onPointerMove = (e: PointerEvent): void => {
  if (!isDragging.value) return
  if (activePointerId !== null && e.pointerId !== activePointerId) return
  if (!modalBox.value) return
  if (isMobile.value) {
    const vh = window.innerHeight
    const minH = Math.round(vh * 0.25)
    const maxH = Math.round(vh * 0.95)
    const dy = e.clientY - startClientY
    let newH = startHeight - dy
    newH = clamp(newH, minH, maxH)
    translateX.value = 0
    translateY.value = 0
    try {
      modalBox.value!.style.setProperty('height', `${newH}px`, 'important')
    } catch {
      modalBox.value!.style.height = `${newH}px`
    }
    return
  }
  return
}

const onPointerUp = (e: PointerEvent): void => {
  if (!isDragging.value) return
  try {
    if (typeof e.pointerId !== 'undefined') dragHandle.value?.releasePointerCapture(e.pointerId)
  } catch (err) {
    void err
  }
  isDragging.value = false
  activePointerId = null
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
  if (isMobile.value) {
    const releaseDy = e.clientY - startClientY
    if (releaseDy > 150) {
      closeModal()
      if (modalBox.value) modalBox.value.style.removeProperty('height')
    }
  }
}

const resetPosition = (): void => {
  translateX.value = 0
  translateY.value = 0
  try {
    if (modalBox.value) modalBox.value.style.removeProperty('height')
  } catch {
    // ignore
  }
}

const modal = useBookmarkedModal()
const {
  loading,
  error,
  warning,
  savedJobs,
  deletedJobs,
  showCopySuccess,
  isOffline,
  showDeleteConfirm,
  removingIds,
  fetchJobs,
  lastSyncTime,
  flushSync,
  handleRefresh,
  handleDeleteAll,
  confirmDeleteAll,
  cancelDeleteAll,
  removeBookmark,
  handleClearDeleted,
  displayedSavedJobs,
  formattedLastSync
} = modal

const closeModal = (): void => {
  emit('update:modelValue', false)
}

const handleKeydown = (e: KeyboardEvent): void => {
  if (e.key === 'Escape' && props?.modelValue) closeModal()
}


watch(() => props.modelValue, async (isOpen) => {
  if (isOpen) {
    flushSync()
    await fetchJobs()
    modalEl.value?.showModal()
    if (isMobile.value && modalBox.value) {
      const vh = window.innerHeight
      const initialHeight = Math.round(vh * 0.6)
      modalBox.value.style.setProperty('height', `${initialHeight}px`, 'important')
    }
  } else {
    modalEl.value?.close()
    resetPosition()
  }
})

watch(() => showDeleteConfirm.value, (isOpen) => {
  if (isOpen) deleteConfirmModal.value?.showModal()
  else deleteConfirmModal.value?.close()
})

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  if (mobileMq) {
    mqListener = (ev: MediaQueryListEvent): void => { isMobile.value = ev.matches }
    isMobile.value = mobileMq.matches
    mobileMq.addEventListener('change', mqListener)
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
  try {
    if (dragHandle.value && activePointerId !== null) dragHandle.value.releasePointerCapture(activePointerId)
  } catch (e) { void e }
  resetPosition()
  if (mobileMq && mqListener) {
    mobileMq.removeEventListener('change', mqListener)
    mqListener = null
  }
})
</script>
