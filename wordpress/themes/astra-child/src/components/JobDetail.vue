<template>
  <div class="space-y-8">
    <!-- Job Title -->
    <section v-if="job.title" class="top-0 backdrop-blur text-center">
      <h1 class="text-3xl !font-bold">{{ job.title }}</h1>
      <div v-if="job.post_time" class="text-sm mt-2 flex items-center justify-center gap-2 font-semibold text-center">
        <i class="fas fa-clock text-blue-500"></i>
        <span>Diupdate {{ computedTimeAgo }}</span>
      </div>
    </section>

    <div v-if="job.title" class="divider"></div>

    <!-- Nama Perusahaan -->
    <section v-if="job.namaPerusahaan">
      <h2 class="text-2xl flex items-center gap-2 !mb-4">
        <i class="fas fa-user-tie text-blue-500"></i>
        <span class="!font-bold">{{ job.namaPerusahaan }}</span>
      </h2>
      <div class="divider"></div>
    </section>

    <!-- Tentang Perusahaan -->
    <section v-if="job.tentangPerusahaan">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-map-marker-alt text-blue-600"></i>
        <span class="font-bold">Tentang Perusahaan</span>
      </h2>
      <Viewer v-html="job.tentangPerusahaan" @click="onWysiwygImgClick" />
      <div class="divider"></div>
    </section>

    <!-- Ringkasan Pekerjaan -->
    <section v-if="job.ringkasanPekerjaan && job.ringkasanPekerjaan.length">
      <h2 class="flex items-center gap-2 !mb-4">
        <i class="fas fa-clipboard-check text-blue-600"></i>
        <span class="font-bold">Ringkasan Pekerjaan</span>
      </h2>
      <div class="gap-4 mt-4">
        <div class="gap-x-6 gap-y-4 text-lg">
          <div v-for="row in job.ringkasanPekerjaan" :key="row.label"
            class="flex items-start lg:space-x-2 space-x-1 mb-2">
            <i :class="`fas ${row.icon} text-blue-600 w-3 text-justify pt-2`"></i>
            <span class="ml-3 !font-semibold whitespace-nowrap min-w-[120px]">{{ row.label }}</span>
            <span :class="[getLabelClass(row.label), '!font-semibold']">:</span>
            <span class="!font-semibold" v-html="row.value"></span>
          </div>
        </div>
      </div>
      <div class="divider"></div>
    </section>

    <!-- Deskripsi Pekerjaan -->
    <section v-if="job.deskripsiPekerjaan">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="font-bold">Deskripsi Pekerjaan</span>
      </h2>
      <Viewer v-html="job.deskripsiPekerjaan" @click="onWysiwygImgClick" />
      <div class="divider"></div>
    </section>

    <!-- Persyaratan -->
    <section v-if="job.persyaratan">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-check-circle text-blue-600"></i>
        <span class="font-bold">Persyaratan</span>
      </h2>
      <Viewer v-html="job.persyaratan" @click="onWysiwygImgClick" />
      <div class="divider"></div>
    </section>

    <!-- Cara Melamar -->
    <section v-if="job.caraMelamar">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-file-signature text-blue-600"></i>
        <span class="font-bold">Cara Melamar</span>
      </h2>
      <Viewer v-html="job.caraMelamar" @click="onWysiwygImgClick" />
      <div class="divider"></div>
    </section>

    <!-- Benefit -->
    <section v-if="job.benefit">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-hand-holding-heart text-blue-600"></i>
        <span class="font-bold">Benefit</span>
      </h2>
      <Viewer v-html="job.benefit" @click="onWysiwygImgClick" />
      <div class="divider"></div>
    </section>

    <!-- Kontak -->
    <section v-if="job.contacts && job.contacts.length">
      <h2 class="text-xl flex items-center justify-between !mb-4">
        <span class="flex items-center gap-2">
          <i class="fas fa-address-card text-blue-600"></i>
          <span class="font-bold">Kontak</span>
        </span>
      </h2>
      <div class="grid grid-cols-1 gap-4 mt-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
          <div v-for="contact in job.contacts" :key="contact.label" class="flex items-center">
            <i :class="`${contact.icon} text-blue-600 w-6 text-center text-xl`"></i>
            <div class="ml-2 font-semibold text-md">
              <span class="block font-semibold">{{ contact.label }}:</span>
              <a :href="contact.href" target="_blank" rel="noopener noreferrer"
                class="block font-semibold break-all max-w-xs whitespace-normal">{{ contact.value }}</a>
            </div>
          </div>
        </div>
      </div>
      <div class="divider"></div>
    </section>

    <!-- Sosial Media -->
    <section v-if="job.social_media && job.social_media.length">
      <h2 class="text-xl flex items-center gap-2 !mb-4">
        <i class="fas fa-address-book text-blue-600"></i>
        <span class="font-bold">Sosial Media</span>
      </h2>
      <div class="grid grid-cols-1 gap-4 mt-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
          <div v-for="item in job.social_media" :key="item.platform" class="flex items-center">
            <i :class="`${item.icon} text-blue-600 w-6 text-center text-xl`"></i>
            <div class="ml-2 font-semibold text-md">
              <span class="block">{{ item.platform }}:</span>
              <a :href="item.url" target="_blank" rel="noopener noreferrer"
                class="block font-semibold break-all max-w-xs whitespace-normal">
                {{ item.platform === 'Whatsapp' ? formatPhone(item.username) : item.username }}
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="divider"></div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import 'viewerjs/dist/viewer.css'
import { component as Viewer } from 'v-viewer'
import { useTimeAgo } from '@/composables/useTime'
import { formatPhone } from '@/services/Formatting'
import type { SingleOverlayResponse } from '@/types'

const props = defineProps<{
  job: SingleOverlayResponse
}>()

const { timeAgo } = useTimeAgo(props.job.post_time)
const computedTimeAgo = computed(() => timeAgo.value)

function onWysiwygImgClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (target.tagName === 'IMG' && target.parentElement?.tagName === 'A') {
    e.preventDefault()
  }
}

function getLabelClass(label: string) {
  switch (label) {
    case 'Jenis Pekerjaan':
      return 'lg:ml-3 ml-5'
    case 'Pendidikan':
    case 'Pengalaman':
    case 'Gender':
    case 'Usia':
    case 'Deadline':
    case 'Gaji':
    case 'Lokasi':
      return 'ml-5'
    default:
      return 'ml-4'
  }
}
</script>