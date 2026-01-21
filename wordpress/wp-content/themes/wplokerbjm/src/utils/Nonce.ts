import { getThemeData } from "./environment";

export class NonceManager {
    private static nonce: string | null = null;

    /**
     * Synchronously reads any available nonce from inline theme dataprops.
     */
    private static readStorage(): string | null {
        const themeNonce = getThemeData()?.wpRestNonce;
        if (themeNonce && themeNonce.length > 0) {
            NonceManager.nonce = themeNonce;
            return NonceManager.nonce;
        }

        return null;
    }

    public static get getNonce(): string | null {
        if (NonceManager.nonce !== null && NonceManager.nonce.length > 0) return NonceManager.nonce;
        return NonceManager.readStorage();
    }
}