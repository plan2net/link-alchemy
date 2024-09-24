# TYPO3 Extension `link_alchemy`

## What does it do?

The `link_alchemy` extension rewrites external links in RTE text fields that refer to internal TYPO3 pages or files. These links are converted to internal TYPO3 links (`t3://page...`, `t3://file...`).

## Installation and configuration

To install the extension, use Composer:

```bash
composer require plan2net/link-alchemy
```

No configuration is required. The extension automatically hooks into the RTE content transformation process.

## Compatibility

Versions 12.0.0 and higher are compatible with the corresponding TYPO3 versions.

If you already overwrite the class `RteHtmlParser` in your project, you may need to adjust the XCLASS configuration in `ext_localconf.php`.

## Usage

The extension automatically hooks into the RTE content transformation process. No additional setup is required. When RTE content is processed, external links that refer to internal pages or files will be rewritten to use TYPO3's internal link format.

## Example

Here is an example of how the extension transforms a link:

- **Before**: `<a href="https://example.com/internal-page">Link</a>`
- **After**: `<a href="t3://page?uid=123">Link</a>`

This ensures that links are correctly handled within the TYPO3 CMS environment, providing better integration and consistency.

## Limitations

The extension only works with RTE content transformations. It does not handle links in other contexts, such as content element input fields.
You may use the already existing [`uri2link`](https://github.com/georgringer/uri2link) extension for for other fields of `renderType=inputLink`.

## Internals

### `Plan2net\LinkAlchemy\Xclass\RteHtmlParser`

This class extends the core `RteHtmlParser` to include custom transformations for internal links.

### `Plan2net\LinkAlchemy\RteTransformation\InternalLinkTransformation`

This class handles the transformation of internal links within the RTE content.

