import type { WPLokerBJMThemedData } from "@/types";

class ThemeManager {
  themeProps: WPLokerBJMThemedData | undefined = $state(undefined);
  public get getThemeData(): WPLokerBJMThemedData | undefined {
    if (this.themeProps !== undefined) return this.themeProps;

    return undefined;
  }

  public setThemeData(data: WPLokerBJMThemedData): void {
    this.themeProps = data;
  }
}

export const themeManager = new ThemeManager();
