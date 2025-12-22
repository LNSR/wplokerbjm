import { openDB, type IDBPDatabase } from 'idb'
import type { CardJob } from '@/types'

class IDB {
  protected DB_NAME: string = 'JobBookmarks'
  protected DB_VERSION: number = 1
  protected STORE_NAME: string = 'bookmarks'

  private dbPromise: Promise<IDBPDatabase<CardJob>> | null = null

  async getDB(): Promise<IDBPDatabase<CardJob>> {
    if (!this.dbPromise) {
      this.dbPromise = openDB<CardJob>(this.DB_NAME, this.DB_VERSION, {
        upgrade: (db) => {
          if (!db.objectStoreNames.contains(this.STORE_NAME)) {
            db.createObjectStore(this.STORE_NAME)
          }
        }
      })
    }
    return this.dbPromise
  }
}

export class BookmarkIDB extends IDB {
  async saveBookmarks(jobs: CardJob[]): Promise<void> {
    try {
      const db = await this.getDB()
      const tx = db.transaction(this.STORE_NAME, 'readwrite')
      await tx.store.clear()
      for (const job of jobs) {
        await tx.store.put(job, job.id)
      }
      await tx.done
    } catch (error) {
      if (error instanceof DOMException && error.name === 'QuotaExceededError') {
        console.error('IndexedDB quota exceeded. Clearing old bookmarks to free space.')
        // Attempt to clear and retry once
        await this.clearBookmarks()
        throw new Error('Storage quota exceeded. Please refresh and try again.')
      }
      throw error
    }
  }

  async addBookmark(job: CardJob): Promise<void> {
    try {
      const db = await this.getDB()
      await db.put(this.STORE_NAME, job, job.id)
    } catch (error) {
      if (error instanceof DOMException && error.name === 'QuotaExceededError') {
        console.error('IndexedDB quota exceeded. Clearing old bookmarks to free space.')
        await this.clearBookmarks()
        throw new Error('Storage quota exceeded. Please refresh and try again.')
      }
      throw error
    }
  }

  async removeBookmark(id: number): Promise<void> {
    try {
      const db = await this.getDB()
      await db.delete(this.STORE_NAME, id)
    } catch (error) {
      console.error('Failed to remove bookmark from IndexedDB:', error)
      throw error
    }
  }

  async loadBookmarks(): Promise<CardJob[]> {
    try {
      const db = await this.getDB()
      return await db.getAll(this.STORE_NAME)
    } catch (error) {
      console.error('Failed to load bookmarks from IndexedDB:', error)
      return []
    }
  }

  async clearBookmarks(): Promise<void> {
    try {
      const db = await this.getDB()
      await db.clear(this.STORE_NAME)
    } catch (error) {
      console.error('Failed to clear bookmarks from IndexedDB:', error)
      throw error
    }
  }
}

export const bookmarkIDB = new BookmarkIDB()