import { test as playwright } from '@playwright/test'
import { administrator, forgetDatabase, freshDatabase, opened } from './database.js'

let taken = 0

// Each test gets its own copy of the seeded database; a request header tells TYPO3 which one
export const test = playwright.extend<{ databaseNumber: number, storedField: (table: string, uid: number, field: string) => string }>({
  databaseNumber: [async ({}, use, info) => {
    const named = info.parallelIndex * 10000 + (taken += 1)

    freshDatabase(named)

    await use(named)

    forgetDatabase(named)
  }, { auto: true }],

  storedField: async ({ databaseNumber }, use) => {
    await use((table, uid, field) => {
      const database = opened(databaseNumber)
      try {
        const row = database.prepare(`SELECT "${field}" AS value FROM "${table}" WHERE uid = ?`).get(uid)

        return String(row?.['value'] ?? '')
      } finally {
        database.close()
      }
    })
  },

  contextOptions: async ({ contextOptions, databaseNumber, baseURL }, use) => {
    const session = administrator()
    const site = new URL(baseURL ?? '')

    await use({
      ...contextOptions,
      extraHTTPHeaders: {
        ...contextOptions.extraHTTPHeaders,
        'x-link-alchemy-database': String(databaseNumber),
      },
      storageState: {
        cookies: [{
          name: session.name,
          value: session.value,
          domain: site.hostname,
          path: '/',
          expires: -1,
          httpOnly: true,
          secure: site.protocol === 'https:',
          sameSite: 'Lax',
        }],
        origins: [],
      },
    })
  },
})

export { expect } from '@playwright/test'
