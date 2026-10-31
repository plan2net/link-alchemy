# Changelog

## 14.1.0

### Changed

- Links in rich text are now rewritten when the record is saved, not when it is opened in the editor.
  Saving an old record without changes still fixes its links.
- The XCLASS of `RteHtmlParser` is gone, so the extension no longer conflicts with other extensions that
  replace this class.
- Links to translated pages use `_language=1` instead of `L=1`. Existing `L=` links keep working.

### Fixed

- Target, class and title of a link field are kept (`https://… _blank class "Title"`).
- Paths like `/about` in link fields of new records are resolved.

### Removed

- `Plan2net\LinkAlchemy\Service\UrlParser`, `Plan2net\LinkAlchemy\Xclass\RteHtmlParser` and
  `Plan2net\LinkAlchemy\RteTransformation\InternalLinkTransformation`. Use `Plan2net\LinkAlchemy\LinkResolver`
  to turn a URL into a `t3://` link.

### Tests

- Functional tests and browser tests against a real TYPO3 backend run on TYPO3 12.4, 13.4 and 14
  with PHP 8.1 to 8.5.
