import { browser, version } from "$app/environment";

export class BaseBroadcastChannel
{
    #SVELTEKIT_BUILD_VERSION = version;
    #channel?: BroadcastChannel;

    constructor(channelName: string)
    {
        if (!browser) return;
        this.#channel = new BroadcastChannel(channelName);
    }

    protected get getChannel(): BroadcastChannel | undefined
    {
        return this.#channel;
    }

    public get currentVersion(): string
    {
        return this.#SVELTEKIT_BUILD_VERSION;
    }
}