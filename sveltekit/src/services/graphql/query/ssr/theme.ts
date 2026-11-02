import { graphql } from "#src/services/graphql/config/tada.js";

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
      wpGraphqlNonce
    }
  }
`);
