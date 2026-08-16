# Contributing

Contributions are welcome! Here's how you can help:

## Reporting Bugs

- Use the [GitHub issue tracker](https://github.com/ethsam/symfony-dropzone/issues)
- Include Symfony and PHP version
- Provide a minimal reproduction case

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).

## Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feat/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass: `vendor/bin/phpunit`
5. Follow PSR-12 coding standards
6. Commit with conventional format: `feat:`, `fix:`, `docs:`
7. Open a Pull Request

CI runs the suite on PHP 8.1 to 8.4 against Symfony 5.4, 6.4 and 7.

## The one rule about the widget template

`src/Resources/views/Form/dropzone.html.twig` renders a `<script>` block that
contains no interpolated value. Anything the browser needs goes into the JSON
config carried by the `data-dropzone-config` attribute, and is read from
`config` inside the script.

Twig's automatic escaping is HTML escaping, which does not make a value safe
inside JavaScript. Filenames come from whoever uploads a file, so a single
`{{ ... }}` inside that script is an injection point.
`tests/Form/DropzoneWidgetRenderTest.php` fails if one appears.

New option? Add it to `configureOptions` with a `setAllowedTypes`, expose it in
`buildDropzoneOptions`, and read it from `config.options` in the script.

## Development Setup

```bash
git clone https://github.com/ethsam/symfony-dropzone.git
cd symfony-dropzone
composer install
vendor/bin/phpunit
```

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
