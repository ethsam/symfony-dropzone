# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- The widget no longer interpolates application data into its inline JavaScript.
  Stored filenames are attacker controlled, and Twig's HTML escaping does not
  protect a JavaScript string context: a filename ending in a backslash escaped
  the closing quote and ran on into the surrounding code. Everything now travels
  as JSON inside an HTML attribute and is read with `JSON.parse`, so the script
  block is a constant. See [SECURITY.md](SECURITY.md). Affects every version up
  to 2.0.0 and the original `emr-dev/symfony-dropzone`.
- `tests/Form/DropzoneWidgetRenderTest.php` renders the widget with hostile
  values and fails if any of them reaches the script block.
- The file id is now URL encoded before being placed in the remove request, and
  the hidden input lookup no longer builds a CSS selector out of it.

### Added

- `maxFilesize` option, mapping to Dropzone.js `maxFilesize` in MiB. Thanks to
  [@nicodemuz](https://github.com/nicodemuz) for the proposal in #3.
- `symfony-dropzone:init`, `symfony-dropzone:sending` and
  `symfony-dropzone:removedfile` DOM events, so
  applications can extend the widget without overriding the template. This
  covers the use case behind #2, appending a value computed in the browser to
  the upload, without injecting JavaScript from PHP.
- `SECURITY.md` with a reporting channel and the rendering rule the bundle
  follows.
- Continuous integration on PHP 8.1 to 8.4 and Symfony 5.4, 6.4 and 7, plus a
  dependency audit. Third party actions are pinned to a commit SHA.

### Changed

- Options are type checked with `setAllowedTypes`. A value of the wrong type
  now raises an exception at form build time instead of being serialised into
  the page.
- Filenames containing `'`, `&`, `<` or `>` are displayed correctly. They used
  to be shown as HTML entities, for instance `O&#039;Brien.pdf`.
- The widget renders one extra element, `<span hidden data-dropzone-config>`,
  next to the form field.

## [2.0.0] - 2026-04-25

### Changed
- **BREAKING**: Minimum PHP version is now 8.1
- **BREAKING**: Minimum Symfony version is now 5.4
- Removed jQuery dependency, the template uses vanilla JavaScript only
- Removed hardcoded DOM references (`#add_property_arrayIdMedia`)
- Proper `transform()` implementation in `DropzoneTransformer`
- Complete `composer.json` with all required dependencies
- Typed properties and return types throughout
- Bundle type changed from `library` to `symfony-bundle`

### Added
- `.gitattributes` for clean Composer installs
- Bundle configuration tree with default options
- Original author attribution in LICENSE
- PHPUnit test suite

### Fixed
- `use App\Entity\File` removed from transformer (broke in non-App namespaces)
- Undeclared `$options` property in transformer (PHP 8.2 deprecation)
- Boolean values in Twig template now render correctly (`true`/`false` instead of `1`/`0`)
- Wrong namespace in `dropzone.php` service config

### Removed
- jQuery dependency
- Hardcoded `#add_property_arrayIdMedia` references

## [1.0.0] - 2022-08-22

### Added
- Initial release (fork of emr-dev/symfony-dropzone)
- DropzoneType form type
- Dropzone.js integration with file upload/remove handlers
