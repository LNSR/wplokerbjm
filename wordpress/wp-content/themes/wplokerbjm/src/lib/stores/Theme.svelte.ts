import typia from "typia";
import type { WPLokerBJMThemedData } from "@/types";

class ThemeManager {
  #themeProps = $state<WPLokerBJMThemedData | undefined>(undefined);

  public get getThemeData(): WPLokerBJMThemedData {
    if (!this.#themeProps) throw new Error("Theme data is not set");
    return typia.assertEquals<WPLokerBJMThemedData>(this.#themeProps);
  }

  public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] {
    try {
      if (typia.is<string>(this.#themeProps?.wpRestNonce)) {
        return typia.assertEquals<string>(this.#themeProps?.wpRestNonce);
      }
      return undefined;
    } catch (err) {
      console.warn("ThemeManager.getNonce: invalid theme data", err);
      return undefined;
    }
  }

  public set setThemeData(data: WPLokerBJMThemedData) {
    if (typia.validateEquals<WPLokerBJMThemedData>(data)) {
      this.#themeProps = data;
    }
  }

  public set setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]) {
    typia.assertEquals<string>(nonce);

    if (!this.#themeProps) return;
    this.#themeProps.wpRestNonce = nonce;
  }
}

export const themeManager = new ThemeManager();