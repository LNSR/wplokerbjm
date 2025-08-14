export class TimeService {
  static getTimeAgo(postTime?: string): { text: string; nextUpdate: number } {
    if (!postTime) return { text: '', nextUpdate: 60000 }
    const postDate = new Date(postTime)
    if (isNaN(postDate.getTime())) return { text: '', nextUpdate: 60000 }
    const now = new Date()
    const diff = Math.floor((now.getTime() - postDate.getTime()) / 1000)
    let nextUpdate = 60000
    let text = ''

    if (diff < 60) {
      text = `${diff} detik lalu`
      nextUpdate = 1000
    } else if (diff < 3600) {
      text = `${Math.floor(diff / 60)} menit lalu`
      nextUpdate = (60 - (diff % 60)) * 1000
    } else if (diff < 86400) {
      text = `${Math.floor(diff / 3600)} jam lalu`
      nextUpdate = (3600 - (diff % 3600)) * 1000
    } else if (diff < 604800) {
      text = `${Math.floor(diff / 86400)} hari lalu`
      nextUpdate = (86400 - (diff % 86400)) * 1000
    } else if (diff < 2592000) {
      text = `${Math.floor(diff / 604800)} minggu lalu`
      nextUpdate = (604800 - (diff % 604800)) * 1000
    } else if (diff < 31536000) {
      text = `${Math.floor(diff / 2592000)} bulan lalu`
      nextUpdate = (2592000 - (diff % 2592000)) * 1000
    } else {
      text = `${Math.floor(diff / 31536000)} tahun lalu`
      nextUpdate = (31536000 - (diff % 31536000)) * 1000
    }

    return { text, nextUpdate }
  }
}