import { expect, test } from '../fixtures/test.js'

const cases = [
  { entered: 'https://link-alchemy.example/about', stored: 't3://page?uid=2' },
  { entered: 'https://link-alchemy.example/fileadmin/welcome.txt', stored: 't3://file?uid=1' },
  { entered: 'https://elsewhere.example/about', stored: 'https://elsewhere.example/about' },
]

for (const { entered, stored } of cases) {
  test(`a link field holding ${entered} is saved as ${stored}`, async ({ page, storedField }) => {
    await page.goto('/typo3/record/edit?edit[tt_content][1]=edit')
    const form = page.frameLocator('#typo3-contentIframe')

    await form.getByRole('textbox', { name: /\[header_link\]/ }).fill(entered)
    await form.getByRole('button', { name: 'Save', exact: true }).click()

    await expect.poll(() => storedField('tt_content', 1, 'header_link')).toBe(stored)
  })
}
