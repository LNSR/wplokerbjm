import { graphql } from "@/services/graphql/config/tada";

export const GET_THEME_DATA = graphql(`
  query GetThemeData {
    themeData {
      siteIconTags
      logo {
        logoUrl
        logoSrcset
        logoSizes
        logoDecoding
        logoWidth
        logoHeight
        }
      wpRestNonce
    }
  }
`);

export const GET_THEME_NONCE = graphql(`
  query GetThemeNonce {
    themeData {
      wpRestNonce
    }
  }
`);