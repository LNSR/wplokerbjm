import type { WPLokerBJMThemedData } from "@/types";

class ThemeManager
{
  #themeProps = $state<WPLokerBJMThemedData | undefined>( undefined );

  public get getThemeData(): WPLokerBJMThemedData
  {
    if ( !this.#themeProps ) throw new Error( "Theme data is not set" );
    return this.#themeProps;
  }

  public get getNonce(): WPLokerBJMThemedData[ "wpRestNonce" ]
  {
    if ( !this.#themeProps ) return undefined;
    return this.#themeProps.wpRestNonce;
  }

  public set setThemeData( data: WPLokerBJMThemedData )
  {
    this.#themeProps = data;
  }

  public set setNonce( nonce: WPLokerBJMThemedData[ "wpRestNonce" ] )
  {
    if ( !this.#themeProps ) return;
    this.#themeProps.wpRestNonce = nonce;
  }
}

export const themeManager = new ThemeManager();