import { gql } from 'urql';

// GraphQL queries for taxonomy terms using JSON scalar
export const GET_ALL_TERMS = gql`
  query GetAllTerms {
    taxonomyTerms {
      lokasiTerms
      genderTerms
      pendidikanTerms
    }
  }
`;

export const GET_LOKASI_TERMS = gql`
  query GetLokasiTerms {
    lokasiTerms
  }
`;

export const GET_GENDER_TERMS = gql`
  query GetGenderTerms {
    genderTerms
  }
`;

export const GET_PENDIDIKAN_TERMS = gql`
  query GetPendidikanTerms {
    pendidikanTerms
  }
`;