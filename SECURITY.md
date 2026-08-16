# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 2.1.x   | Yes       |
| 2.0.x   | No, upgrade to 2.1 |
| 1.x     | No        |

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Use GitHub's private reporting on this repository
(Security tab, "Report a vulnerability"), or write to etheve.samuel@gmail.com
with `symfony-dropzone` in the subject.

Include the affected version, the smallest reproduction you can manage, and what
an attacker gets out of it. Expect a first answer within 5 working days. If the
report is confirmed, a fixed release and a GitHub advisory go out together, and
you are credited unless you ask otherwise.

## Design rule this bundle follows

The widget renders a `<script>` block. That block is a constant: no application
data is ever interpolated into it. Everything the browser needs travels as JSON
inside an HTML attribute and is read back with `JSON.parse`.

The reason is that Twig's automatic escaping is HTML escaping. It is the right
defence inside an attribute and the wrong one inside a script, where entities
are never decoded and a backslash is enough to break out of a string literal.

`tests/Form/DropzoneWidgetRenderTest.php` enforces this: it renders the widget
with hostile stored values and asserts the script block comes out identical.
A pull request that interpolates a value into the script will fail there. Send
data through the JSON payload instead, and read it from `config` in the script.

## Fixed in 2.1.0

Versions up to and including 2.0.0, and the original `emr-dev/symfony-dropzone`
this bundle forks, built the widget's JavaScript by interpolating stored values
into it, including the filename of every already uploaded file:

```twig
name: '{{ file.filename }}',
```

Filenames come from whoever uploads a file. Twig escapes them for HTML, which
leaves the JavaScript string context unprotected: a filename ending in a
backslash cancels the closing quote and the string runs on into the surrounding
code.

- **Impact:** at minimum the widget's script fails to parse, so the upload field
  is dead for every user who opens that record. With control over more than one
  filename the quotes can be realigned, which turns it into stored cross site
  scripting running in the session of whoever opens the form, often an
  administrator.
- **Also affected:** `file.size` was interpolated outside of any quotes, so an
  entity exposing a non numeric size wrote code directly into the page.
- **Visible symptom without any attack:** a filename containing `'`, `&`, `<` or
  `>` was displayed to users as an HTML entity, for example `O&#039;Brien.pdf`.
- **Fix:** 2.1.0 moves the whole payload out of the script, as described above.
- **Workaround if you cannot upgrade:** sanitise filenames on upload to
  `[A-Za-z0-9._-]` before storing them, and do the same for the property used by
  `choice_src`.

If you keep a copy of `dropzone.html.twig` under
`templates/bundles/SymfonyDropzoneBundle/`, that copy overrides the bundle and
is still vulnerable after upgrading. Delete it or port your changes.

Reported by the maintainer while reviewing the widget's rendering. A GitHub
advisory is published alongside the 2.1.0 release so installed projects are
notified through their dependency scanner.
