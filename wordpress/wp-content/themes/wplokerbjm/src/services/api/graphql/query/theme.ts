import { graphql } from 'gql.tada';

export const GET_THEME_DATA = graphql(`
  query GetThemeData {
    themeData {
      data {
        siteIconTags
        logo {
          logoUrl
          logoSrcset
          logoSizes
          logoDecoding
          logoWidth
          logoHeight
        }
        disableTracking
        wpRestNonce
      }
    }
  }
`);

export const GET_THEME_NONCE = graphql(`
  query GetThemeNonce {
    themeData {
      data {
        wpRestNonce
      }
    }
  }
`);

export const GET_JWT = graphql(`
  mutation GetJWT($username: String, $password: String, $token: String) {
    jwt(username: $username, password: $password, token: $token)
  }
`);
