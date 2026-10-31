import { copyFileSync, existsSync, readFileSync, rmSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { fileURLToPath } from 'node:url'

const held = resolve(dirname(fileURLToPath(import.meta.url)), '../../../var/e2e')

const seed = resolve(held, 'seed.sqlite')

const databaseFile = (named: number): string => resolve(held, `typo3-${String(named)}.sqlite`)

const beside = ['-wal', '-shm']

// Wait if the backend still has the file locked
export const opened = (databaseNumber: number): DatabaseSync => {
  const database = new DatabaseSync(databaseFile(databaseNumber))
  database.exec('PRAGMA busy_timeout = 5000')

  return database
}

export const freshDatabase = (named: number): void => {
  if (!existsSync(seed)) {
    throw new Error(`No database at ${seed}. Build one: composer test:e2e:seed`)
  }

  for (const log of beside) {
    rmSync(databaseFile(named) + log, { force: true })
  }

  copyFileSync(seed, databaseFile(named))
}

export const forgetDatabase = (named: number): void => {
  for (const part of ['', ...beside]) {
    rmSync(databaseFile(named) + part, { force: true })
  }
}

export const administrator = (): { name: string, value: string } => {
  const noted = resolve(held, 'session.json')

  if (!existsSync(noted)) {
    throw new Error(`No session at ${noted}. Build one: composer test:e2e:seed`)
  }

  return JSON.parse(readFileSync(noted, 'utf8')) as { name: string, value: string }
}
