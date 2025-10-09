import { readable } from 'svelte/store'
import { MediaQuery } from 'svelte/reactivity'

export const mobileMq = (typeof window !== 'undefined') ? new MediaQuery('(max-width: 767.98px)') : null

export const isMobile = readable(false, (set) => {
    const update = () => {
        if (mobileMq) {
            set(mobileMq.current)
        } else {
            try {
                set(!!window.matchMedia('(max-width: 767.98px)').matches)
            } catch {
                set(false)
            }
        }
    }

    update()

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', update)
        return () => window.removeEventListener('resize', update)
    }
})