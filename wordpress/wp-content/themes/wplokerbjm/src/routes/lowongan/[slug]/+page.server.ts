import type { PageServerLoad } from "./$types";
import { error } from '@sveltejs/kit';
import { APIServiceServer, APIServiceShared } from "@/services/APIService";
import { getCmsOrigin } from "@/utils/environment";
import { schemaScriptAttach } from "$lib/server/utils/scripts.server";
export const load: PageServerLoad = async ( { params, locals, url, fetch } ) =>
{
  const slug = String( params.slug ?? "" );
  if ( !slug ) throw error( 410, "Lowongan tidak ditemukan" );

  const isMobile = Boolean( locals.deviceType?.isMobile );

  try
  {
    const jobPromise = APIServiceServer.fetchJobDetailGraphQL( slug, undefined, fetch );
    const schemaPromise = APIServiceServer.fetchJobSchemasGraphQL( slug, undefined, "JobPosting", fetch ).catch(
      ( e ) =>
      {
        console.warn( "Failed to fetch job schema on server load:", e );
        return null;
      },
    );

    const desktopDataPromise = !isMobile
      ? Promise.all( [
        APIServiceShared.fetchCarouselGraphQL( fetch ),
        APIServiceShared.fetchJobGridGraphQL( { paged: 1 }, fetch ),
      ] ).catch( ( e ) =>
      {
        console.warn( "Failed to fetch carousel/jobGrid on server load:", e );
        return [ null, null ] as const;
      } )
      : Promise.resolve( [ null, null ] as const );

    const [ job, schemas, [ carousel, jobGrid ] ] = await Promise.all( [
      jobPromise,
      schemaPromise,
      desktopDataPromise,
    ] );

    let jobSchema = schemas?.[ 0 ] || null;
    if ( jobSchema && url.origin )
    {
      const cmsOrigin = getCmsOrigin();
      try
      {
        const hostOnly = cmsOrigin.replace( /^https?:\/\//, "" ).replace( /\/$/, "" );
        const originRegex = new RegExp( `https?:\\/\\/${ hostOnly }`, "g" );
        const str = JSON.stringify( jobSchema );
        jobSchema = str.replace( originRegex, url.origin );
      } catch ( e )
      {
        console.warn( "Failed to replace job schema URLs, using original", e );
      }
    }

    if ( !isMobile )
    {
      if ( !job ) throw error( 410, "Lowongan tidak ditemukan" );

      return {
        carousel: carousel ?? { jobs: [], totalJobs: 0 },
        jobGrid: jobGrid ?? { jobs: [], maxNumPages: 1, totalJobs: 0 },
        job,
        jobSchemaScript: jobSchema
          ? schemaScriptAttach( jobSchema, "JobPosting", `jobposting-${ job.id }` )
          : "",
      };
    } else
    {
      if ( !job ) throw error( 410, "Lowongan tidak ditemukan" );
      return {
        job,
        jobSchemaScript: jobSchema
          ? schemaScriptAttach( jobSchema, "JobPosting", `jobposting-${ job.id }` )
          : "",
      };
    }
  } catch ( err )
  {
    console.error( "+page.server load error for /lowongan/[slug]:", err );
    throw error( 410, "Lowongan tidak ditemukan" );
  }
};
