<template>
  <div class="relative" tabindex="0" @blur="close" ref="dropdownRef">
    <button
      type="button"
      class="w-full text-left !px-4 !py-3 border rounded h-12 !bg-[var(--ast-global-color-5)] hover:!bg-[var(--ast-global-color-1) !text-[var(--ast-global-color-1)]"
      @click="toggle"
    >
      <span class="!pl-6">
        {{ multiSelectLabel || placeholder }}
      </span>
      <i
        class="fas fa-chevron-right float-right !mt-1 transition-transform"
        :class="{ 'rotate-90': open }"
      ></i>
    </button>
    <ul
      v-show="open"
      class="absolute left-0 right-0 mt-1 border rounded shadow-lg z-30 max-h-96 overflow-auto py-2 !bg-[var(--ast-global-color-5)]"
    >
      <!-- Search input -->
      <li class="!px-5 !py-2 sticky top-0 z-50 bg-[var(--ast-global-color-5)]">
        <input
          v-model="search"
          type="text"
          class="w-full border rounded px-3 py-2 text-sm focus:outline-none"
          placeholder="Cari..."
          @keydown.stop
        />
      </li>
      <!-- Sticky Breadcrumb with icons -->
      <li
        v-if="breadcrumb.length > 0 && !search"
        class="!px-5 !py-2 mb-2 flex items-center text-sm !text-[var(--ast-global-color-1)] rounded-t bg-[var(--ast-global-color-5)] sticky top-10 z-40 border-b border-gray-200"
        style="backdrop-filter: blur(2px);"
      >
        <div class="flex items-center gap-2 flex-1 min-w-0 overflow-x-auto">
          <i class="fas fa-sitemap text-blue-600 !mr-2 shrink-0"></i>
          <template v-for="(crumb, idx) in breadcrumb" :key="idx">
            <span
              v-if="idx < breadcrumb.length - 1"
              class="cursor-pointer hover:underline font-medium flex items-center whitespace-nowrap"
              @mousedown.stop.prevent="goToBreadcrumb(idx)"
            >
              <i class="fas fa-folder-open !mr-1 text-yellow-500"></i>
              {{ crumb }}
            </span>
            <span
              v-else
              class="font-bold flex items-center !text-[var(--ast-global-color-1)] whitespace-nowrap"
            >
              <i class="fas fa-folder !mr-1"></i>
              {{ crumb }}
            </span>
            <span v-if="idx < breadcrumb.length - 1" class="mx-1 text-gray-400 shrink-0">
              <i class="fas fa-chevron-right"></i>
            </span>
          </template>
        </div>
      </li>
      <!-- Clear filter button -->
      <li
        v-if="(isMultiple && selectedValues.length && !(selectedValues.length === 1 && selectedValues[0]?.value === SEMUA_VALUE)) || (breadcrumb.length > 0 && !search)"
        class="flex justify-between items-center !px-5 !py-1"
      >
        <button
          v-if="breadcrumb.length > 0 && !search"
          type="button"
          class="flex items-center !gap-1 !px-2 !py-0.5 rounded !text-sm bg-blue-50 hover:bg-blue-100 text-blue-600 !font-medium transition"
          @mousedown.stop.prevent="goBack"
        >
          <i class="fas fa-arrow-left text-xs"></i>
          Kembali
        </button>
        <span></span>
        <button
          v-if="isMultiple && selectedValues.length && !(selectedValues.length === 1 && selectedValues[0]?.value === SEMUA_VALUE)"
          type="button"
          class="text-blue-600 hover:underline !font-medium !text-sm !px-2 !py-0.5"
          @click="emit('update:modelValue', [SEMUA_VALUE])"
        >
          Hapus filter ini
        </button>
      </li>
      <!-- Options list -->
      <li
        v-for="(option, idx) in filteredOptions.filter(opt => opt.value !== '')"
        :key="option.__key"
        :class="[
          'flex items-center !px-5 !py-2 cursor-pointer select-none transition rounded text-left',
          idx === activeIndex ? '!bg-[var(--ast-global-color-1)]/15' : '',
          isSelected(option.value) ? 'font-bold' : ''
        ]"
      >
        <span
          class="flex-1 text-left flex items-center"
          @mouseenter="activeIndex = idx"
          @click="!isMultiple && select(option)"
        >
          <label class="flex items-center cursor-pointer">
            <input
              v-if="isMultiple"
              type="checkbox"
              class="!mr-3 w-6 h-6 accent-blue-500"
              :checked="isSelected(option.value)"
              @change="toggleValue(option.value, option.label)"
              tabindex="-1"
            />
          </label>
          <i
            v-if="option.children && option.children.length"
            class="fas fa-folder !mr-2 text-yellow-400"
          ></i>
          <i
            v-else
            class="fas fa-file-alt !mr-2 text-gray-400"
          ></i>
          <!-- Highlight search matches in label -->
          <span v-if="search && search.trim()"
                v-html="highlightMatch(option.label, search)">
          </span>
          <span v-else>
            {{ option.label }}
          </span>
          <span v-if="option.__breadcrumbs && search" class="ml-2 text-xs text-gray-400 italic">
            ({{ option.__breadcrumbs.join(' / ') }})
          </span>
          <span v-if="option.isLoading" class="ml-2 text-xs text-gray-400 italic">
            Memuat...
          </span>
        </span>
        <!-- Chevron to navigate into children -->
        <button
          v-if="option.children && option.children.length && !search"
          class="!ml-2 flex items-center justify-center w-10 h-10 rounded hover:!bg-blue-300 relative"
          @mousedown.stop.prevent="navigateChildren(option.children, option.label, option)"
          tabindex="-1"
          aria-label="Lihat sub"
        >
          <span class="absolute -top-2 -right-1 bg-[var(--ast-global-color-1)] text-white text-xs rounded-full px-2 py-0.1 z-10">
            {{ option.children.length }}
          </span>
          <i class="fas fa-chevron-right text-2xl"></i>
        </button>
      </li>
      <li v-if="filteredOptions.length === 0" class="!px-5 !py-2 text-gray-400 text-center">
        Tidak ada hasil
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, toRef } from 'vue'
import { useDropdown, type Option } from '@/composables/useDropdown'
import type { SortOption } from '@/types';

const props = defineProps<{
  modelValue: string[] | string | SortOption
  options: Option[]
  placeholder?: string
  multiple?: boolean
  disabled?: boolean
}>()
const emit = defineEmits(['update:modelValue', 'open'])

const dropdownRef = ref<HTMLElement | null>(null)

const {
  open,
  activeIndex,
  search,
  breadcrumb,
  selectedValues,
  multiSelectLabel,
  isSelected,
  select,
  toggleValue,
  isMultiple,
  SEMUA_VALUE,
  toggle,
  close,
  goBack,
  navigateChildren,
  goToBreadcrumb,
  filteredOptions,
  highlightMatch,
} = useDropdown({
  modelValue: toRef(props, 'modelValue') as any,
  options: toRef(props, 'options'),
  emit: emit as (event: string, ...args: any[]) => void,
  multiple: props.multiple,
  placeholder: props.placeholder,
})

function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
})
</script>