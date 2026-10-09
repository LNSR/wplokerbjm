import type { WPLokerBJMThemedData } from "@/types";

/**
 * Theme Props Manager
 * Theme props state that come from Wordpress and can be used globally in the app
 */
class ThemePropsManager
{
  #themeProps = $state<WPLokerBJMThemedData | undefined>( undefined );

  // public get getThemeData(): WPLokerBJMThemedData
  // {
  //   if ( !this.#themeProps ) throw new Error( "Theme data is not set" );
  //   return this.#themeProps;
  // }

  public get getNonce(): WPLokerBJMThemedData[ "wpGraphqlNonce" ]
  {
    if ( !this.#themeProps ) return undefined;
    return this.#themeProps.wpGraphqlNonce;
  }

  public set setThemeData( data: WPLokerBJMThemedData )
  {
    this.#themeProps = data;
  }

  public set setNonce( nonce: WPLokerBJMThemedData[ "wpGraphqlNonce" ] )
  {
    if ( !this.#themeProps ) return;
    this.#themeProps.wpGraphqlNonce = nonce;
  }
}

export const themePropsStore = new ThemePropsManager();