export class NonceManager {
    public nonce: string | null = null;
    private storageKey = 'wp-rest-nonce';

    constructor() {
        this.nonce = this.readStorage;
    }

    private get readStorage(): string | null {
        if (typeof sessionStorage === 'undefined') return null;
        try {
            return sessionStorage.getItem(this.storageKey) || null;
        } catch {
            return null;
        }
    }

    public setNonce(nonce: string): void {
        this.nonce = nonce;
        if (typeof sessionStorage !== 'undefined') {
            try {
                sessionStorage.setItem(this.storageKey, nonce);
            } catch {
                console.error('Failed to save nonce to sessionStorage');
            }
        }
    }

    public get getNonce(): string | null {
        return this.nonce;
    }
}

export const nonceStore = new NonceManager();