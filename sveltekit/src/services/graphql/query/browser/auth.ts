import { graphql } from "@/services/graphql/config/tada";
export const GET_JWT = graphql(`
  mutation GetJWT($username: String, $password: String, $token: String) {
    jwt(username: $username, password: $password, token: $token)
  }
`);
