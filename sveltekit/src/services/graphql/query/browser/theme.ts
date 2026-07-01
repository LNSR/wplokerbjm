import { graphql } from "@/services/graphql/config/tada";

export const GET_THEME_NONCE = graphql(`
  query GetThemeNonce {
    themeData {
      wpRestNonce
    }
  }
`);
