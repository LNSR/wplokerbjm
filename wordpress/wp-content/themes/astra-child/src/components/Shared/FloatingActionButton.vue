<template>
  <div class="fixed bottom-6 right-6 z-100 flex flex-col items-end gap-4">
    <!-- Scroll to Top Button -->
    <transition enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
      <button v-show="show" @click="scrollToTop"
        class="btn btn-circle btn-outline btn-xs shadow-lg transition hover:scale-110 bg-white/80 dark:bg-slate-800/80"
        title="Kembali ke Atas" aria-label="Kembali ke Atas">
        <i class="fas fa-arrow-up text-base"></i>
      </button>
    </transition>

    <!-- Contact Dropdown -->
    <div class="relative" ref="dropdownRef">
      <!-- Overlay: place BEFORE the dropdown list -->
      <div v-if="dropdownOpen" class="fixed inset-0 z-40" @click="closeDropdown" tabindex="-1" aria-hidden="true"></div>
      <button
        class="btn btn-primary flex items-center gap-2 rounded-full !px-4 !py-3 cursor-pointer transform transition hover:scale-105 focus:ring-2 focus:ring-blue-400 !bg-[var(--ast-global-color-5)] !text-[var(--ast-global-color-1)]"
        @mousedown.prevent="toggleDropdown" @keydown.enter.space.prevent="toggleDropdown" @keydown.esc="closeDropdown"
        :aria-expanded="dropdownOpen" aria-haspopup="menu" title="Kontak Admin" tabindex="0">
        <i class="fas fa-user-headset"></i>
        <span>Kontak Admin</span>
        <svg class="w-4 h-4 ml-1 transition-transform" :class="{ 'rotate-180': dropdownOpen }" fill="none"
          stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      <transition enter-active-class="transition-opacity transition-transform duration-200"
        leave-active-class="transition-opacity transition-transform duration-200"
        enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0 translate-y-2">
        <div v-show="dropdownOpen"
          class="!backdrop-blur-lg shadow-xl border border-blue-400 rounded-xl p-4 flex flex-col gap-4 w-60 absolute bottom-full mb-2 right-0 z-50 bg-white dark:bg-slate-900"
          role="menu">
          <!-- Kontak Langsung -->
          <!-- <div>
            <div class="text-xs font-bold text-gray-500 uppercase mb-2 tracking-wider flex items-center gap-2">
              <i class="fas fa-phone-alt text-blue-500 text-sm"></i>
              Kontak Langsung
            </div>
            <div class="flex flex-col gap-2">
              <a
                v-for="link in contactLinks"
                :key="link.url"
                :href="link.url"
                target="_blank"
                rel="noopener"
                class="btn btn-outline flex items-center gap-3 rounded-full !px-4 !py-2 transition hover:border-blue-600 hover:scale-105"
                role="menuitem"
                tabindex="0"
              >
                <i :class="`${link.icon} ${link.color} text-xl w-6 text-center`"></i>
                <span class="font-semibold">{{ link.label }}</span>
              </a>
            </div>
          </div> -->

          <!-- Social Media Nested Dropdown -->
          <div class="relative">
            <button @click="socialDropdownOpen = !socialDropdownOpen"
              class="btn btn-outline flex items-center gap32 rounded-full !px-4 !py-2 w-full justify-between transition hover:border-blue-600 hover:scale-105"
              :aria-expanded="socialDropdownOpen" aria-haspopup="menu" type="button">
              <span class="flex items-center gap-2">
                <i class="fas fa-hashtag text-pink-500"></i>
                <span class="font-semibold">Social Media</span>
              </span>
              <svg class="w-4 h-4 ml-1 transition-transform" :class="{ 'rotate-180': socialDropdownOpen }" fill="none"
                stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <transition enter-active-class="transition-opacity transition-transform duration-200"
              leave-active-class="transition-opacity transition-transform duration-200"
              enter-from-class="opacity-0 -translate-y-2" leave-to-class="opacity-0 -translate-y-2">
              <div v-show="socialDropdownOpen"
                class="absolute right-0 bottom-full !mb-2 !w-56 bg-white !px-4 !py-2 dark:bg-slate-900 border border-blue-400 rounded-xl shadow-lg z-50 flex flex-col !gap-2 !p-2"
                role="menu">
                <a v-for="link in socialLinks" :key="link.url" :href="link.url" target="_blank" rel="noopener"
                  class="btn btn-outline flex items-center !gap-3 rounded-full !px-4 !py-2 transition hover:border-blue-600 hover:scale-105"
                  role="menuitem" tabindex="0">
                  <i :class="`${link.icon} ${link.color} text-xl !w-6 text-center`"></i>
                  <span class="font-semibold">{{ link.label }}</span>
                </a>
              </div>
            </transition>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

const show = ref(false)
const dropdownOpen = ref(false)
const socialDropdownOpen = ref(false)
// Optionally close on main dropdown close:
watch(dropdownOpen, (val) => { if (!val) socialDropdownOpen.value = false })
const dropdownRef = ref<HTMLElement | null>(null)

let jobGridObserver: IntersectionObserver | null = null

// const contactLinks = [
//   {
//     url: 'https://wa.me/6283862447271',
//     icon: 'fab fa-whatsapp',
//     label: 'WhatsApp',
//     color: 'text-green-500 dark:text-green-400',
//   }
// ]

const socialLinks = [
  {
    url: 'https://www.instagram.com/loker_banjarmasin',
    icon: 'fab fa-instagram',
    label: 'Instagram',
    color: 'text-pink-500 dark:text-pink-400',
  },
  {
    url: 'https://www.tiktok.com/@loker_banjarmasin',
    icon: 'fab fa-tiktok',
    label: 'TikTok',
    color: 'text-black dark:text-white',
  },
  {
    url: 'https://www.facebook.com/loker.banjarmasin.2025',
    icon: 'fab fa-facebook',
    label: 'Facebook',
    color: 'text-blue-600 dark:text-blue-400',
  },
  {
    url: 'https://www.threads.net/@loker_banjarmasin',
    icon: 'fab fa-threads',
    label: 'Threads',
    color: 'text-black dark:text-white',
  },
]

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
  show.value = false // hide button instantly after scroll
}

function toggleDropdown() {
  dropdownOpen.value = !dropdownOpen.value
  if (dropdownOpen.value) {
    setTimeout(() => {
      dropdownRef.value?.querySelector('a')?.focus()
    }, 50)
  }
}

function closeDropdown() {
  dropdownOpen.value = false
}

function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    closeDropdown()
  }
}

function observeJobGrid() {
  const jobGrid = document.getElementById('job-grid')
  if (!jobGrid) return
  jobGridObserver = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        show.value = true
      }
    },
    { threshold: 0.1 }
  )
  jobGridObserver.observe(jobGrid)
}

function handleScroll() {
  show.value = window.scrollY > 0
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  observeJobGrid()
  window.addEventListener('scroll', handleScroll)
  handleScroll() // initialize state
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  if (jobGridObserver) jobGridObserver.disconnect()
  window.removeEventListener('scroll', handleScroll)
})
</script>
