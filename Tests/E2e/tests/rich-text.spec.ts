import { expect, test } from '../fixtures/test.js'

test('an absolute link to a page in rich text is saved as a page link', async ({ page, storedField }) => {
  await page.goto('/typo3/record/edit?edit[tt_content][2]=edit')
  const form = page.frameLocator('#typo3-contentIframe')
  await expect(form.locator('.ck-editor__editable')).toContainText('About us')

  await form.getByRole('button', { name: 'Save', exact: true }).click()

  await expect.poll(() => storedField('tt_content', 2, 'bodytext')).toContain('href="t3://page?uid=2"')
})
