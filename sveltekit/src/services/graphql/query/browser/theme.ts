import { graphql } from "#src/services/graphql/config/tada.js";

export const GET_THEME_NONCE = graphql(`
  query GetThemeNonce {
    themeData {
      wpGraphqlNonce
    }
  }
`);
