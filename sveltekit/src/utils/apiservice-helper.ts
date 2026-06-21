import typia from "typia";
import type {
    CardJob,
} from "@/types";
import { FRAGMENT_JOB_CARD_FIELDS } from "@/services/graphql/query/shared/job";
import { type FragmentOf, readFragment } from "gql.tada";

export class APIServiceHelper
{
    /**
     * Normalize job data by stripping WP origin from permalink and removing trailing slashes from slug
     */
    public static normalizeJob<J extends FragmentOf<typeof FRAGMENT_JOB_CARD_FIELDS> | null>(job: J): J
    {
        if (!job || typeof job !== "object") 
        {
            return job;
        }
        const unwrap = readFragment(FRAGMENT_JOB_CARD_FIELDS, job);

        // shallow merge normalized fields back into original job object
        //* gql.tada type fragments are immutable so we need to create a new object with normalized fields
        return {
            ...job,
            permalink: APIServiceHelper.normalizePermalink(unwrap.permalink) ?? unwrap.permalink,
            slug: APIServiceHelper.normalizeSlug(unwrap.slug) ?? unwrap.slug,
        };
    };
    /** 
     * GraphQL Taxonomies use JSON Scalar so detect and parse JSON strings in the response 
     * @param jsonString - The JSON string to parse from GraphQL response
     * @returns Parsed object of type T
     * @throws Error if the input is not a string or if JSON parsing fails
     * @remarks This is necessary because the GraphQL API returns taxonomy terms as JSON-encoded strings, which need to be parsed back into objects for use in the application.
    */
    public static parseGQLJSON<T>(jsonString: unknown): T
    {
        try
        {
            typia.assertGuard<string>(jsonString);
            return JSON.parse(jsonString) as T;
        } catch (e)
        {
            console.error("Failed to parse JSON from GraphQL response:", jsonString, e);
            throw new Error("Invalid JSON format in GraphQL response");
        }
    }

    /**
   * Normalize permalink by stripping WP origin and trailing slashes
   * @param permalink 
   * @returns 
   */
    private static normalizePermalink(permalink: CardJob[ 'permalink' ]): string | undefined
    {
        if (!typia.is<string>(permalink)) return undefined;
        let p = permalink.replace(/\/+$/g, "");
        try
        {
            if (p.startsWith("http://") || p.startsWith("https://"))
            {
                p = new URL(p).pathname;
            }
            return p;
        } catch (e)
        {
            console.error("Invalid URL in job permalink:", permalink, e);
            return permalink;
        }
    }

    /**
     * Normalize slug by stripping trailing slashes
     */
    private static normalizeSlug(slug: CardJob[ 'slug' ]): string | undefined
    {
        if (!typia.is<string>(slug)) return undefined;
        return slug.replace(/\/+$/g, "");
    }

}