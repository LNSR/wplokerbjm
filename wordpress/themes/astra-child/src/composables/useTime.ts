import { ref, onMounted, onUnmounted, watch } from 'vue'

export function useTimeAgo(postTime: string | undefined) {
  const timeAgo = ref('')
  let timeoutId: ReturnType<typeof setTimeout> | null = null

  function updateTimeAgo() {
    if (!postTime) {
      timeAgo.value = ''
      return
    }
    const postDate = new Date(postTime)
    if (isNaN(postDate.getTime())) {
      timeAgo.value = ''
      return
    }
    const now = new Date()
    const diff = Math.floor((now.getTime() - postDate.getTime()) / 1000)
    let nextUpdate = 60000

    if (diff < 60) {
      timeAgo.value = `${diff} detik lalu`
      nextUpdate = 1000
    } else if (diff < 3600) {
      timeAgo.value = `${Math.floor(diff / 60)} menit lalu`
      nextUpdate = (60 - (diff % 60)) * 1000
    } else if (diff < 86400) {
      timeAgo.value = `${Math.floor(diff / 3600)} jam lalu`
      nextUpdate = (3600 - (diff % 3600)) * 1000
    } else if (diff < 604800) {
      timeAgo.value = `${Math.floor(diff / 86400)} hari lalu`
      nextUpdate = (86400 - (diff % 86400)) * 1000
    } else if (diff < 2592000) {
      timeAgo.value = `${Math.floor(diff / 604800)} minggu lalu`
      nextUpdate = (604800 - (diff % 604800)) * 1000
    } else if (diff < 31536000) {
      timeAgo.value = `${Math.floor(diff / 2592000)} bulan lalu`
      nextUpdate = (2592000 - (diff % 2592000)) * 1000
    } else {
      timeAgo.value = `${Math.floor(diff / 31536000)} tahun lalu`
      nextUpdate = (31536000 - (diff % 31536000)) * 1000
    }

    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = setTimeout(updateTimeAgo, nextUpdate)
  }

  onMounted(updateTimeAgo)
  onUnmounted(() => {
    if (timeoutId) clearTimeout(timeoutId)
  })

  // React to changes in postTime
  watch(() => postTime, () => {
    updateTimeAgo()
  })

  return { timeAgo }
}