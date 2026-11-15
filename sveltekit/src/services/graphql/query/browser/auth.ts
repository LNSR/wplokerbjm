import { graphql } from "#src/services/graphql/config/tada.js";
export const GET_JWT = graphql(`
  mutation GetJWT($username: String, $password: String, $token: String) {
    jwt(username: $username, password: $password, token: $token)
  }
`);
