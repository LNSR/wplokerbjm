import { openDB, type IDBPDatabase } from 'idb'
import type { CardJob } from '@/types'

const DB_NAME = 'JobBookmarks'
const DB_VERSION = 1
const STORE_NAME = 'bookmarks'

let dbPromise: Promise<IDBPDatabase<CardJob>> | null = null

const getDB = (): Promise<IDBPDatabase<CardJob>> => {
  if (!dbPromise) {
    dbPromise = openDB<CardJob>(DB_NAME, DB_VERSION, {
      upgrade(db) {
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME)
        }
      }
    })
  }
  return dbPromise
}

export const saveBookmarks = async (jobs: CardJob[]): Promise<void> => {
  const db = await getDB()
  const tx = db.transaction(STORE_NAME, 'readwrite')
  await tx.store.clear()
  for (const job of jobs) {
    await tx.store.put(job, job.id)
  }
  await tx.done
}

export const loadBookmarks = async (): Promise<CardJob[]> => {
  const db = await getDB()
  return await db.getAll(STORE_NAME)
}

export const clearBookmarks = async (): Promise<void> => {
  const db = await getDB()
  await db.clear(STORE_NAME)
}