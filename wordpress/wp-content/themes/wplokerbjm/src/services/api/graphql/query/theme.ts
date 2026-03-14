import { gql } from 'urql';

export const GET_THEME_DATA = gql`
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
`;

export const GET_THEME_NONCE = gql`
  query GetThemeData {
    themeData {
      data {
        wpRestNonce
      }
    }
  }
`;


export const GET_JWT = gql`
  mutation GetJWT($username: String, $password: String, $token: String) {
    jwt(username: $username, password: $password, token: $token)
  }
`;
