import type { WPLokerBJMThemedData } from "@/types";

class ThemeManager {
  #themeProps: WPLokerBJMThemedData | undefined = $state(undefined);

  public get getThemeData(): WPLokerBJMThemedData {
    return this.#themeProps!;
  }

  public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] | undefined {
    return this.#themeProps?.wpRestNonce;
  }

  public setThemeData(data: WPLokerBJMThemedData): WPLokerBJMThemedData {
    return this.#themeProps = data;
  }

  public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): WPLokerBJMThemedData["wpRestNonce"] {
    return this.#themeProps!.wpRestNonce = nonce;
  }
}

export const themeManager = new ThemeManager();