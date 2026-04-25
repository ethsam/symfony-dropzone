# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-04-25

### Changed
- **BREAKING**: Minimum PHP version is now 8.1
- **BREAKING**: Minimum Symfony version is now 5.4
- Removed jQuery dependency — template uses vanilla JavaScript only
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
