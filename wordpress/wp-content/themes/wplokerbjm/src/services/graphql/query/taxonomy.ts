import { graphql } from "@/services/graphql/config/tada";


// GraphQL queries for taxonomy terms using JSON scalar
export const GET_ALL_TERMS = graphql(`
  query GetAllTerms {
    taxonomyTerms {
      lokasiTerms
      genderTerms
      pendidikanTerms
    }
  }
`);

export const GET_LOKASI_TERMS = graphql(`
  query GetLokasiTerms {
    lokasiTerms
  }
`);

export const GET_GENDER_TERMS = graphql(`
  query GetGenderTerms {
    genderTerms
  }
`);

export const GET_PENDIDIKAN_TERMS = graphql(`
  query GetPendidikanTerms {
    pendidikanTerms
  }
`);