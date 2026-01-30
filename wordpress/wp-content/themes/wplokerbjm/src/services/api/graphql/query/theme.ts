import { gql } from 'urql';
export const GET_THEME_DATA = gql`
  query GetThemeData {
    themeData {
      data {
        themeUrl
        logo
        logoSrcset
        logoSizes
        logoDecoding
        logoWidth
        logoHeight
        lastJobUpdate
        lastTaxonomyUpdate
        themeVersion
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