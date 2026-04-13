import { browser, version } from "$app/environment";

export class BaseBroadcastChannel
{
    public CURRENT_VERSION = version;
    public channel?: BroadcastChannel;

    constructor(channelName: string)
    {
        if (!browser) return;
        this.channel = new BroadcastChannel(channelName);
    }
}