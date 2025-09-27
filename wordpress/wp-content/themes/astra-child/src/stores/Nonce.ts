import { defineStore } from 'pinia';

export const useNonceStore = defineStore('nonce', {
    state: () => ({
        nonce: null as string | null,
    }),
    getters: {
        getNonce: (state) => {
            if (state.nonce) return state.nonce;
            if (typeof sessionStorage !== 'undefined') {
                try {
                    return sessionStorage.getItem('wp-rest-nonce');
                } catch (error) {
                    console.error('Failed to get nonce from sessionStorage:', error);
                }
            }
            return null;
        },
    },
    actions: {
        setNonce(nonce: string) {
            this.nonce = nonce;
            if (typeof sessionStorage !== 'undefined') {
                try {
                    sessionStorage.setItem('wp-rest-nonce', nonce);
                } catch (error) {
                    console.error('Failed to set nonce in sessionStorage:', error);
                }
            }
        },
    },
});
