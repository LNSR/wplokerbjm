import { openDB, type IDBPDatabase } from 'idb'
import type { CardJob } from '@/types'

class IDB
{
  protected DB_NAME: string = 'wplokerbjm'
  protected DB_VERSION: number = 1
  protected STORE_NAME: string = ''

  constructor ( DB_NAME: string, DB_VERSION: number, STORE_NAME: string )
  {
    this.DB_NAME = DB_NAME
    this.DB_VERSION = DB_VERSION
    this.STORE_NAME = STORE_NAME
  }

  private dbPromise: Promise<IDBPDatabase<CardJob>> | null = null

  protected async getWPLokerBJMDB (): Promise<IDBPDatabase<CardJob>>
  {
    if ( !this.dbPromise )
    {
      this.dbPromise = openDB<CardJob>( this.DB_NAME, this.DB_VERSION, {
        upgrade: ( db ) =>
        {
          if ( !db.objectStoreNames.contains( this.STORE_NAME ) )
          {
            db.createObjectStore( this.STORE_NAME )
          }
        }
      } )
    }
    return this.dbPromise
  }
}

export class BookmarkIDB extends IDB
{

  private async getBookmarkDB (): Promise<IDBPDatabase<CardJob>>
  {
    return super.getWPLokerBJMDB()
  }

  /**
   * Save an array of bookmarked jobs to IndexedDB, replacing existing bookmarks.
   * Handles quota exceeded errors by clearing old bookmarks and retrying once.
   *
   * @param jobs - Array of CardJob objects to save as bookmarks
   * @throws Error if storage quota is exceeded after retrying
   */
  public async saveBookmarks ( jobs: CardJob[] ): Promise<void>
  {
    try
    {
      const db = await this.getBookmarkDB()
      const tx = db.transaction( this.STORE_NAME, 'readwrite' )
      await tx.store.clear()
      for ( const job of jobs )
      {
        await tx.store.put( job, ( Number( job.id ) ) )
      }
      await tx.done
    } catch ( error )
    {
      if ( error instanceof DOMException && error.name === 'QuotaExceededError' )
      {
        console.error( 'IndexedDB quota exceeded. Clearing old bookmarks to free space.' )
        // Attempt to clear and retry once
        await this.clearBookmarks()
        throw new Error( 'Storage quota exceeded. Please refresh and try again.' )
      }
      throw error
    }
  }

  /**
   * Save individual bookmarked job to IndexedDB. 
   * @param job 
   */
  public async addBookmark ( job: CardJob ): Promise<void>
  {
    try
    {
      const db = await this.getBookmarkDB()
      await db.put( this.STORE_NAME, job, Number( job.id ) )
    } catch ( error )
    {
      if ( error instanceof DOMException && error.name === 'QuotaExceededError' )
      {
        console.error( 'IndexedDB quota exceeded. Clearing old bookmarks to free space.' )
        await this.clearBookmarks()
        throw new Error( 'Storage quota exceeded. Please refresh and try again.' )
      }
      throw error
    }
  }

  public async removeBookmark ( id: CardJob[ 'id' ] ): Promise<void>
  {
    try
    {
      const db = await this.getBookmarkDB()
      await db.delete( this.STORE_NAME, id )
    } catch ( error )
    {
      console.error( 'Failed to remove bookmark from IndexedDB:', error )
      throw error
    }
  }

  public async loadBookmarks (): Promise<CardJob[]>
  {
    try
    {
      const db = await this.getBookmarkDB()
      return await db.getAll( this.STORE_NAME )
    } catch ( error )
    {
      console.error( 'Failed to load bookmarks from IndexedDB:', error )
      return []
    }
  }

  public async clearBookmarks (): Promise<void>
  {
    try
    {
      const db = await this.getBookmarkDB()
      await db.clear( this.STORE_NAME )
    } catch ( error )
    {
      console.error( 'Failed to clear bookmarks from IndexedDB:', error )
      throw error
    }
  }
}

export const bookmarkIDB = new BookmarkIDB( 'JobBookmarks', 1, 'bookmarks' )