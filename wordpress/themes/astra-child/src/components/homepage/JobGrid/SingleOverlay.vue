<template>
  <transition name="drawer-fade">
    <div v-if="visible" class="min-h-screen flex flex-col pointer-events-auto ml-7">
      <!-- Overlay background (only in JobGrid area) -->
      <div class="absolute top-0 left-0 right-0 bottom-0" @click="close"></div>
      <!-- Drawer -->
      <aside
        class="relative bg-[var(--ast-global-color-4)] shadow-xl transition-transform duration-300 w-full rounded-xl border-2 border-blue-400 max-h-screen overflow-y-auto flex flex-col z-50"
        :class="drawerOpenClass" :style="`margin-top: ${props.offset ? props.offset + 'px' : '0'};`">
        <!-- <a v-if="overlay && overlay.id" :href="`/wp-admin/post.php?post=${overlay.id}&action=edit`" target="_blank"
          rel="noopener" class="absolute top-5 left-4 btn btn-sm btn-outline btn-primary flex items-center gap-1"
          title="Edit Job">
          <i class="fas fa-edit"></i>
          Edit
        </a> -->
        <button class="absolute top-5 right-4" @click="close" aria-label="Close">
          Tutup
        </button>
        <a
          v-if="!loading && overlay && overlay.permalink"
          :href="overlay.permalink"
          target="_blank"
          rel="noopener"
          class="absolute top-5 left-4 btn btn-sm btn-outline btn-primary flex items-center gap-1"
        >
          <i class="fas fa-external-link-alt"></i>
          Buka di Tab Baru
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
          <!-- Job Title -->
          <section class="top-0 backdrop-blur text-center">
            <h1 class="text-3xl !font-bold">{{ overlay.title }}</h1>
          </section>
          <div class="divider"></div>

          <!-- Nama Perusahaan -->
          <section v-if="overlay.namaPerusahaan">
            <h2 class="text-2xl flex items-center gap-2 !mb-4">
              <i class="fas fa-user-tie text-blue-500"></i>
              <span class="!font-bold">{{ overlay.namaPerusahaan }}</span>
            </h2>
            <div class="divider"></div>
          </section>

          <!-- Tentang Perusahaan -->
          <section v-if="overlay.tentangPerusahaan">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-map-marker-alt text-blue-600"></i>
              <span class="font-bold">Tentang Perusahaan</span>
            </h2>
            <div v-html="overlay.tentangPerusahaan"></div>
            <div class="divider"></div>
          </section>

          <!-- Ringkasan Pekerjaan -->
          <section v-if="overlay.summaryRows && overlay.summaryRows.length">
            <h2 class="flex items-center gap-2 !mb-4">
              <i class="fas fa-clipboard-check text-blue-600"></i>
              <span class="font-bold">Ringkasan Pekerjaan</span>
            </h2>
            <div class="gap-4 mt-4">
              <div class="gap-x-6 gap-y-4 text-lg">
                <div v-for="row in overlay.summaryRows" :key="row.label"
                  class="flex items-start lg:space-x-2 space-x-1 mb-2">
                  <i :class="['fas', row.icon, 'text-blue-600', 'w-3', 'text-justify', 'pt-2']"></i>
                  <span class="ml-3 !font-semibold whitespace-nowrap min-w-[120px]">{{ row.label }}</span>
                  <span class="ml-2 !font-semibold">:</span>
                  <span class="!font-semibold" v-html="row.value"></span>
                </div>
              </div>
            </div>
            <div class="divider"></div>
          </section>

          <!-- Deskripsi Pekerjaan -->
          <section v-if="overlay.deskripsiPekerjaan">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-info-circle text-blue-600"></i>
              <span class="font-bold">Deskripsi Pekerjaan</span>
            </h2>
            <div v-html="overlay.deskripsiPekerjaan"></div>
            <div class="divider"></div>
          </section>

          <!-- Persyaratan -->
          <section v-if="overlay.persyaratan">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-check-circle text-blue-600"></i>
              <span class="font-bold">Persyaratan</span>
            </h2>
            <div v-html="overlay.persyaratan"></div>
            <div class="divider"></div>
          </section>

          <!-- Cara Melamar -->
          <section v-if="overlay.caraMelamar">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-file-signature text-blue-600"></i>
              <span class="font-bold">Cara Melamar</span>
            </h2>
            <div v-html="overlay.caraMelamar"></div>
            <div class="divider"></div>
          </section>

          <!-- Benefit -->
          <section v-if="overlay.benefit">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-hand-holding-heart text-blue-600"></i>
              <span class="font-bold">Benefit</span>
            </h2>
            <div v-html="overlay.benefit"></div>
            <div class="divider"></div>
          </section>

          <!-- Kontak -->
          <section v-if="overlay.contactRows && overlay.contactRows.length">
            <h2 class="text-xl flex items-center justify-between !mb-4">
              <span class="flex items-center gap-2">
                <i class="fas fa-address-card text-blue-600"></i>
                <span class="font-bold">Kontak</span>
              </span>
            </h2>
            <div class="grid grid-cols-1 gap-4 mt-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                <div v-for="(contact, idx) in overlay.contactRows" :key="idx" class="flex items-center">
                  <i :class="[contact.icon, 'text-blue-600', 'w-6', 'text-center', 'text-xl']"></i>
                  <div class="ml-2 font-semibold text-md">
                    <span class="block font-semibold">{{ contact.label }}:</span>
                    <a :href="contact.href" target="_blank" rel="noopener noreferrer"
                      class="block font-semibold break-all w-full">
                      {{ contact.value }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <div class="divider"></div>
          </section>

          <!-- Sosial Media -->
          <section v-if="overlay.social_media && overlay.social_media.length">
            <h2 class="text-xl flex items-center gap-2 !mb-4">
              <i class="fas fa-address-book text-blue-600"></i>
              <span class="font-bold">Sosial Media</span>
            </h2>
            <div class="grid grid-cols-1 gap-4 mt-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                <div v-for="(item, idx) in overlay.social_media" :key="idx" class="flex items-center">
                  <i :class="[item.icon, 'text-blue-600', 'w-6', 'text-center', 'text-xl']"></i>
                  <div class="ml-2 font-semibold text-md">
                    <span class="block">{{ item.platform }}:</span>
                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="block break-all w-full">
                      {{ item.platform === 'Whatsapp' ? formatPhone(item.username) : item.username }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <div class="divider"></div>
          </section>
        </div>
      </aside>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useSingleOverlay } from '@/composables/useSingleOverlay'
import { formatPhone } from '@/services/Formatting'

const props = defineProps<{
  id?: number
  visible?: boolean
  offset?: number
}>()
const emit = defineEmits(['close'])

const { data, loading, error, fetchSingleOverlay } = useSingleOverlay()
const route = useRoute()

// Fetch by id (overlay) or by slug (route)
function fetchJob() {
  if (props.visible && props.id) {
    fetchSingleOverlay(props.id)
  } else if (route.params.slug) {
    const slugParam = Array.isArray(route.params.slug) ? route.params.slug[0] : route.params.slug
    const id = Number(slugParam)
    if (!isNaN(id)) {
      fetchSingleOverlay(id)
    }
  }
}

watch(
  () => props.id,
  fetchJob,
  { immediate: true }
)

watch(
  () => props.visible,
  fetchJob
)

onMounted(fetchJob)

const overlay = data

function close() {
  emit('close')
}

const drawerOpenClass = 'transform translate-x-0'
</script>

<style scoped>
.drawer-fade-enter-active,
.drawer-fade-leave-active {
  transition: opacity 0.2s, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-fade-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.drawer-fade-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>