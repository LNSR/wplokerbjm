import { initGraphQLTada } from 'gql.tada';
import type { introspection } from '@/graphql-env.d.ts';

export const graphql = initGraphQLTada<{
    disableMasking: false;
    scalars: {
        JSON: string;
    },
    introspection: introspection;
}>();