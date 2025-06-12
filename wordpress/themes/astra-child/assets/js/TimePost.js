document.addEventListener('alpine:init', () => {
    window.timePost = timePost;
    function timePost(time) {
        return {
            time,
            timeAgo: 'Loading...',
            interval: null,
            update() {
                // Handle both Unix timestamp and ISO string
                let postDate;
                if (typeof this.time === 'string' && this.time.match(/^\d+$/)) {
                    postDate = new Date(parseInt(this.time) * 1000);
                } else if (typeof this.time === 'number') {
                    postDate = new Date(this.time * 1000);
                } else {
                    postDate = new Date(this.time);
                }
                
                const now = new Date();
                const diff = Math.floor((now - postDate) / 1000);

                if (diff < 5) {
                    this.timeAgo = 'Baru saja';
                    this.setNextUpdate(5000);
                    return;
                }

                const timeFormats = [
                    { limit: 60,        divisor: 1,        label: 'detik lalu',   next: 1000 },
                    { limit: 3600,      divisor: 60,       label: 'menit lalu',   next: (diff) => (60 - (diff % 60)) * 1000 },
                    { limit: 86400,     divisor: 3600,     label: 'jam lalu',     next: (diff) => (3600 - (diff % 3600)) * 1000 },
                    { limit: 604800,    divisor: 86400,    label: 'hari lalu',    next: (diff) => (86400 - (diff % 86400)) * 1000 },
                    { limit: 2592000,   divisor: 604800,   label: 'minggu lalu',  next: (diff) => (604800 - (diff % 604800)) * 1000 },
                    { limit: 31536000,  divisor: 2592000,  label: 'bulan lalu',   next: (diff) => (2592000 - (diff % 2592000)) * 1000 },
                    { limit: Infinity,  divisor: 31536000, label: 'tahun lalu',   next: (diff) => (31536000 - (diff % 31536000)) * 1000 }
                ];

                for (const format of timeFormats) {
                    if (diff < format.limit) {
                        const value = Math.floor(diff / format.divisor);
                        this.timeAgo = `${value} ${format.label}`;
                        const nextUpdate = typeof format.next === 'function' ? format.next(diff) : format.next;
                        this.setNextUpdate(Math.max(nextUpdate, 1000));
                        break;
                    }
                }
            },
            setNextUpdate(ms) {
                if (this.interval) clearTimeout(this.interval);
                this.interval = setTimeout(() => this.update(), ms);
            },
            init() {
                this.update();
            },
            destroy() {
                if (this.interval) {
                    clearTimeout(this.interval);
                    this.interval = null;
                }
            }
        }
    }
});