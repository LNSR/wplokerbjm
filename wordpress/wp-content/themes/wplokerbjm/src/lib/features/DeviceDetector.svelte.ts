import { createSubscriber } from "svelte/reactivity";
import { on } from "svelte/events";
import typia from "typia";
type Device = "desktop" | "mobile";
class DeviceDetector
{
    #mediaQuery?: MediaQueryList;
    #initialDeviceSSR?: Device; // set by root layout during SSR.
    #onDeviceChange?: () => void;
    #deviceSubscriber = createSubscriber((update) =>
    {
        if (typeof window === "undefined") return;
        this.#mediaQuery = window.matchMedia("(max-width: 768px)");
        const handler = () =>
        {
            this.#onDeviceChange && this.#onDeviceChange(); //* side effect when device type changes;
            update();
        }
        const off = on(this.#mediaQuery!, "change", handler);
        return () => off();
    });

    public get currentDevice(): Device
    {
        this.#deviceSubscriber?.();
        if (typeof window === "undefined")
            return typia.assertEquals<Device>(this.#initialDeviceSSR === "mobile" ? "mobile" : "desktop");
        return this.#mediaQuery?.matches ? "mobile" : "desktop";
    }

    public get isPlatformMobile(): boolean { return this.currentDevice === "mobile"; }

    public get isPlatformDesktop(): boolean { return this.currentDevice === "desktop"; }

    /**
     * set on +layout.svelte during SSR to ensure correct initial device type on first render and prevent hydration mismatch
     * @internal
     */
    public set initialDeviceSSR(value: Device) { this.#initialDeviceSSR ??= value; }

    /**
     * callback passed when device type changes
     * * used to clear caches in route store to prevent cross-device data issues when changing device type 
     * @internal
     */
    public set setCallbackOnDeviceChange(cb: () => void) { this.#onDeviceChange ??= cb; }
}
export type DeviceDetectorInternal = DeviceDetector; // Prevent access to the setters from component layer except root
export const deviceDetector = new DeviceDetector() as Omit<DeviceDetector, "initialDeviceSSR" | "setCallbackOnDeviceChange">;