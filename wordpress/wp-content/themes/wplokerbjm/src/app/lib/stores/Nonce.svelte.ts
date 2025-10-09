import { toStore, type Readable } from 'svelte/store';

export class NonceManager {
    private storageKey = 'wp-rest-nonce';
    public nonce = $state<string | null>(null);
    public readonly store: Readable<string | null>;

    constructor() {
        // toStore setter delegates to setNonce so store.set(...) persists
        this.store = toStore(() => this.nonce, (v: string | null) => {
            this.setNonce(v as string);
        });

        const initial = this.readStorage();
        if (initial) this.nonce = initial;

        // Keep sessionStorage dynamically in sync if the rune is mutated
        // through direct assignment (bypassing setNonce) or other means.
        this.store.subscribe((v) => {
            // avoid redundant writes: if storage already reflects the value, no-op
            const stored = this.readStorage();
            if (v === stored) return;
            if (typeof sessionStorage === 'undefined') return;
            try {
                if (v === null || v === undefined) {
                    sessionStorage.removeItem(this.storageKey);
                } else {
                    sessionStorage.setItem(this.storageKey, v);
                }
            } catch {
                console.error('Failed to sync nonce to sessionStorage');
            }
        });
    }

    private readStorage(): string | null {
        if (typeof sessionStorage === 'undefined') return null;
        try {
            return sessionStorage.getItem(this.storageKey);
        } catch {
            console.error('Failed to read nonce from sessionStorage');
            return null;
        }
    }

    public getNonce(): string | null {
        const stored = this.readStorage();
        if (stored) {
            return stored;
        }
        return this.nonce;
    }

    public setNonce(nonce: string): void {
        if (typeof sessionStorage !== 'undefined') {
            try {
                sessionStorage.setItem(this.storageKey, nonce);
            } catch {
                console.error('Failed to save nonce to sessionStorage');
            }
        }
        this.nonce = nonce;
    }

    // Svelte store subscribe to integrate with components
    public subscribe(run: (value: string | null) => void) {
        return this.store.subscribe(run);
    }
}

export const nonceStore = new NonceManager();