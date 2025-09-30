<template>
    <div class="relative flex items-center">
        <span @click.prevent.stop="handleToggleSave" @mouseenter="isHovered = true" @mouseleave="isHovered = false"
            :disabled="isLoading" :class="[
                'rounded-full transition-colors',
                buttonSizeClass,
                bookmarkStyle.style,
                { '!opacity-50 cursor-not-allowed': isLoading }
            ]" :title="isSaved(props.jobId) ? 'Hapus bookmark' : 'Simpan lowongan'" aria-label="Bookmark job"
            :aria-pressed="isSaved(props.jobId)" role="button">
            <i :class="displayedIconClass"></i>
        </span>

        <!-- hover bubble asking for confirmation: 'Simpan?' or 'Hapus?' -->
        <div v-if="isHovered && !isLoading" class="absolute -top-8 right-0 flex items-center pointer-events-none">
            <div class="bg-[var(--ast-global-color-1)] text-white text-xs font-semibold px-2 py-1 rounded shadow-sm">
                {{ isSaved(props.jobId) ? 'Hapus?' : 'Simpan?' }}
            </div>
        </div>

        <!-- confirmation bubble with fade/slide animation -->
        <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
            <div v-if="confirmationState !== null" class="absolute -top-8 right-0 flex items-center" role="status"
                aria-live="polite">
                <div v-if="confirmationState === 'saved'"
                    class="!bg-green-600 text-white text-xs font-semibold !px-2 !py-1 rounded !shadow-sm">
                    Tersimpan
                </div>
                <div v-else-if="confirmationState === 'removed'"
                    class="!bg-gray-700 text-white text-xs font-semibold !px-2 !py-1 rounded !shadow-sm">
                    Terhapus
                </div>
            </div>
        </transition>

        <!-- error bubble -->
        <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
            <div v-if="errorState !== null" class="absolute -top-8 right-0 flex items-center" role="alert"
                aria-live="assertive">
                <div class="!bg-red-600 text-white text-xs font-semibold !px-2 !py-1 rounded !shadow-sm">
                    {{ errorState === 'save' ? 'Gagal menyimpan' : 'Gagal menghapus' }}
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useBookmark } from '@/composables/useBookmark'

const props = defineProps<{
    jobId: number
    variant?: 'carousel' | 'featured' | 'detail'
}>()

const { isSaved, toggleSave } = useBookmark()

const confirmationState = ref<'saved' | 'removed' | null>(null)
const errorState = ref<'save' | 'remove' | null>(null)
const isLoading = ref(false) // prevent multiple rapid clicks
const isHovered = ref(false)
const isPending = ref(false)
const preToggleSaved = ref(false)

const handleToggleSave = async () => {
    if (isLoading.value) return
    isLoading.value = true
    preToggleSaved.value = isSaved(props.jobId)
    isPending.value = true
    try {
        const wasSaved = isSaved(props.jobId)
        await toggleSave(props.jobId)
        isPending.value = false

        // If we just saved the job, show 'saved' confirmation
        if (!wasSaved && isSaved(props.jobId)) {
            confirmationState.value = 'saved'
        }

        if (wasSaved && !isSaved(props.jobId)) {
            confirmationState.value = 'removed'
        }

        if (confirmationState.value !== null) {
            setTimeout(() => {
                confirmationState.value = null
            }, 1000)
        }

        await new Promise(resolve => setTimeout(resolve, 1000));
    } catch {
        isPending.value = false
        const wasSaved = preToggleSaved.value
        errorState.value = wasSaved ? 'remove' : 'save'
        setTimeout(() => {
            errorState.value = null
        }, 3000)
    } finally {
        isLoading.value = false
    }
}

const buttonSizeClass = computed(() => {
    switch (props.variant) {
        case 'carousel':
            return '!btn-sm'
        case 'featured':
        case 'detail':
            return '!btn-md'
        default:
            return '!btn-md'
    }
})


const useBookmarkStyle = (isSaved: boolean, showConfirmation: boolean): { style: string } => {
    if (!isSaved) {
        return {
            style: 'text-gray-600'
        }
    }

    if (showConfirmation) {
        return {
            style: 'text-green-700'
        }
    }

    return {
        style: 'text-red-700'
    }
}


const bookmarkStyle = computed(() => useBookmarkStyle(isSaved(props.jobId), confirmationState.value === 'saved'))

const displayedIconClass = computed(() => {
    if (isPending.value) {
        return preToggleSaved.value ? 'fas fa-trash text-lg text-red-400' : 'fas fa-bookmark text-lg text-[var(--ast-global-color-1)]'
    }
    const saved = isSaved(props.jobId)
    if (confirmationState.value === 'saved') return 'fas fa-bookmark'
    return saved ? 'fas fa-trash text-lg text-red-400' : 'fas fa-bookmark text-lg text-[var(--ast-global-color-1)]'
})
</script>
