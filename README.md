# Symfony Dropzone Bundle

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ethsam/symfony-dropzone.svg)](https://packagist.org/packages/ethsam/symfony-dropzone)
[![License](https://img.shields.io/packagist/l/ethsam/symfony-dropzone.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/ethsam/symfony-dropzone.svg)](composer.json)

> Seamless integration of Dropzone.js into Symfony Forms with automatic entity relationship management for drag-and-drop file uploads.

**[EN](README.md) | [FR](docs/README.fr.md) | [ES](docs/README.es.md)**

## Features

- **Drag-and-drop file uploads**: Powered by Dropzone.js
- **Entity relationship support**: Automatically manage OneToMany and ManyToOne associations
- **Built-in data transformation**: IDs to entities via Doctrine ORM
- **Pre-populated edit forms**: Show existing files in edit mode
- **Fully configurable**: Dropzone.js options exposed in form builder
- **Single or multiple files**: Control upload mode per form field
- **Custom upload/remove handlers**: Route-based endpoints with JSON responses
- **Image resizing**: Client-side image processing before upload
- **Flexible authentication**: Custom headers for API integration
- **Symfony Flex compatible**: Automatic bundle registration

## Requirements

- **PHP**: ≥8.1
- **Symfony**: 5.4, 6.x, 7.x
- **Doctrine ORM**: 2.12+
- **Dropzone.js**: 6.0+ (included via CDN)

## Installation

### Step 1: Install via Composer

```bash
composer require ethsam/symfony-dropzone
```

The bundle registers automatically with Symfony Flex. If you're not using Flex, add to `config/bundles.php`:

```php
Ethsam\SymfonyDropzone\SymfonyDropzoneBundle::class => ['all' => true],
```

### Step 2: Include Dropzone.js

Add the following to your base template (e.g., `base.html.twig`):

```html
<link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet" type="text/css" />
<script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
```

That's it! You're ready to use `DropzoneType` in your forms.

## Quick Start

### 1. Define Your File Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 255)]
    private string $src = ''; // URL or path to file

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function setSrc(string $src): self
    {
        $this->src = $src;
        return $this;
    }
}
```

### 2. Define Your Main Entity with Relationship

```php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    // OneToMany relationship
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'post', cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }
        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        $this->attachments->removeElement($attachment);
        return $this;
    }

    public function getAttachments(): Collection
    {
        return $this->attachments;
    }
}
```

### 3. Create a Form Type

```php
namespace App\Form;

use App\Entity\Attachment;
use App\Entity\Post;
use Ethsam\SymfonyDropzone\Form\DropzoneType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Post Title',
            ])
            ->add('attachments', DropzoneType::class, [
                'class' => Attachment::class,
                'maxFiles' => 5,
                'multiple' => true,
                'uploadHandler' => 'app_upload_file',
                'removeHandler' => 'app_remove_file',
                'acceptedFiles' => 'image/*,.pdf',
                'addRemoveLinks' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
```

### 4. Create Upload/Remove Handlers

```php
namespace App\Controller;

use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileController extends AbstractController
{
    // These two routes are called by the widget, they are not protected by it.
    // Add your own access control, and validate the upload server side: the
    // acceptedFiles and maxFilesize options only filter in the browser.
    #[IsGranted('ROLE_USER')]
    #[Route('/upload', name: 'app_upload_file', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        // Never build the stored name from the client one, and never trust the
        // client mime type. guessExtension() reads the actual file contents.
        $filename = uniqid() . '.' . $uploadedFile->guessExtension();
        $uploadedFile->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads',
            $filename
        );

        // Create and persist the attachment
        $attachment = new Attachment();
        $attachment->setFilename($uploadedFile->getClientOriginalName());
        $attachment->setSrc('/uploads/' . $filename);

        $em->persist($attachment);
        $em->flush();

        return new JsonResponse(['id' => $attachment->getId()]);
    }

    // The id comes straight from the page, so check that this user is allowed
    // to delete this particular file. Without that check any authenticated
    // visitor can delete anyone's files by changing the id.
    #[IsGranted('DELETE', subject: 'attachment')]
    #[Route('/remove/{id}', name: 'app_remove_file', methods: ['DELETE'])]
    public function remove(Attachment $attachment, EntityManagerInterface $em): JsonResponse
    {
        $id = $attachment->getId();

        // Optionally delete the file from disk
        // unlink($this->getParameter('kernel.project_dir') . '/public' . $attachment->getSrc());

        $em->remove($attachment);
        $em->flush();

        return new JsonResponse(['id' => $id]);
    }
}
```

### 5. Use the Form in Your Template

```twig
{# templates/post/create.html.twig #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>Create Post</h1>

    {{ form_start(form) }}
        {{ form_widget(form.title) }}
        {{ form_widget(form.attachments) }}
        <button type="submit">Create</button>
    {{ form_end(form) }}
{% endblock %}
```

That's it! The bundle handles everything:
- Dropzone.js widget rendering
- File upload via AJAX
- File ID storage in hidden fields
- Entity relationship transformation on form submission

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `class` | string | null | **Required.** Entity class for file/attachment objects |
| `multiple` | bool | true | Enable multiple file mode; set `false` for single file (ManyToOne) |
| `maxFiles` | int | 1 | Maximum number of files allowed in the dropzone |
| `maxFilesize` | int\|float\|null | null | Maximum file size in MiB, rejected in the browser before upload. `null` keeps Dropzone's own default. This is a convenience for the user, not a security control: always enforce the limit again in your upload handler |
| `uploadHandler` | string | null | **Required.** Symfony route name for file upload endpoint |
| `removeHandler` | string | null | **Required.** Symfony route name for file removal endpoint |
| `uploadHandlerMethod` | string | "POST" | HTTP method for upload requests |
| `removeHandlerMethod` | string | "DELETE" | HTTP method for remove requests |
| `choice_src` | string | "src" | Entity property name containing file URL/path (getter method: `get{PropertyName}()`) |
| `acceptedFiles` | string | null | MIME types accepted (e.g., `"image/*,.pdf"`) |
| `addRemoveLinks` | bool | true | Show "Remove" link on file previews |
| `headers` | array | [] | Custom HTTP headers sent with requests (e.g., `['Authorization' => 'Bearer TOKEN']`) |
| `formData` | array | [] | Additional form data sent with upload request |
| `withCredentials` | int | 0 | XHR `withCredentials` setting (0 or 1) |
| `thumbnailWidth` | int | 120 | Width of preview thumbnails in pixels |
| `thumbnailHeight` | int | 120 | Height of preview thumbnails in pixels |
| `thumbnailMethod` | string | "crop" | Thumbnail scaling: `"crop"` or `"contain"` |
| `resizeWidth` | int | null | Client-side resize width before upload (preserves aspect ratio if only one set) |
| `resizeHeight` | int | null | Client-side resize height before upload |
| `resizeMimeType` | string | null | Output MIME type after resize (e.g., `"image/jpeg"`) |
| `resizeMethod` | string | "contain" | Resize scaling: `"crop"` or `"contain"` |
| `filesizeBase` | int | 1024 | Base unit for filesize calculations |
| `ignoreHiddenFiles` | bool | true | Ignore hidden files in directories |
| `autoProcessQueue` | bool | true | Auto-process upload queue on file addition |
| `autoQueue` | bool | true | Auto-queue files when added to dropzone |
| `previewsContainer` | string | null | CSS selector for custom preview container (e.g., `"#my-previews"`) |
| `required` | bool | true | Field is required for form validation |

Every option is type checked when the form is built. Passing a value of the wrong
type raises an `InvalidOptionsException` instead of being written into the page.

## JavaScript Events

The widget dispatches DOM events on the dropzone element, so you can extend the
behaviour without overriding the template or injecting JavaScript from PHP.
Events bubble, so listening on `document` is enough.

| Event | Fired when | `event.detail` |
|-------|-----------|----------------|
| `symfony-dropzone:init` | The Dropzone instance is ready | `{ dropzone, config }` |
| `symfony-dropzone:sending` | Just before a file is uploaded | `{ dropzone, file, xhr, formData }` |
| `symfony-dropzone:removedfile` | After a file is removed | `{ file }` |

The `symfony-dropzone:` prefix is not an accident. Dropzone.js 6 dispatches its
own DOM events named `dropzone:sending`, `dropzone:success` and so on, with a
`detail` of `{args: [...]}`. Those are still available and unaffected; the
events above are the bundle's, with a named detail, and they work the same on
Dropzone 5 which has no DOM events at all.

Adding a value computed in the browser to every upload:

```html
<script>
    var uuid = crypto.randomUUID();

    document.addEventListener('symfony-dropzone:sending', function (event) {
        event.detail.formData.append('uuid', uuid);
    });
</script>
```

Reaching the Dropzone instance itself, for anything the options do not cover:

```html
<script>
    document.addEventListener('symfony-dropzone:init', function (event) {
        event.detail.dropzone.on('error', function (file, message) {
            console.warn('upload failed', file.name, message);
        });
    });
</script>
```

The `symfony-dropzone:init` listener has to be registered before the widget renders,
typically in the page head or in a script loaded with `defer`. The other events
fire on user interaction, so they can be bound at any time.

## File Entity Requirements

Your file/attachment entity must implement:

- **`getId(): ?int`**: Returns the unique identifier
- **`getFilename(): string`**: Returns the filename for display
- **Getter for `choice_src` property**: By default `getSrc(): string`, returns the file URL/path for thumbnail display

Example minimal entity:

```php
#[ORM\Entity]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    #[ORM\Column(length: 255)]
    private string $src = '';

    public function getId(): ?int { return $this->id; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): self { $this->filename = $filename; return $this; }
    public function getSrc(): string { return $this->src; }
    public function setSrc(string $src): self { $this->src = $src; return $this; }
}
```

## How It Works

### Architecture Overview

1. **Form Type Registration**: `DropzoneType` extends Symfony's form system
2. **Hidden Fields**: For multiple files: a `CollectionType` with hidden inputs; for single: an `EntityType` field
3. **Twig Template**: Renders Dropzone.js widget with JavaScript configuration
4. **Upload Flow**:
   - User drags files or clicks to select
   - Dropzone.js sends each file to your `uploadHandler` route via AJAX
   - Handler persists entity to database, returns `{"id": <int>}`
   - Bundle stores file ID in hidden form field
5. **Form Submission**: Hidden field values are collected
6. **Data Transformation**: `DropzoneTransformer` converts IDs back to entity objects via Doctrine
7. **Persistence**: Form submission handles OneToMany/ManyToOne relationships automatically

### File Removal Flow

1. User clicks "Remove" link on file preview
2. Dropzone.js sends DELETE (or POST) to `removeHandler` route
3. Handler deletes entity, returns `{"id": <int>}`
4. Widget removes preview from DOM
5. On next form submission, removed ID is not included, relationship is updated

## Examples

### Basic Multiple File Upload

```php
$builder->add('attachments', DropzoneType::class, [
    'class' => Attachment::class,
    'multiple' => true,
    'maxFiles' => 10,
    'maxFilesize' => 50, // MiB, rejected in the browser before upload
    'uploadHandler' => 'app_upload_file',
    'removeHandler' => 'app_remove_file',
]);
```

### Single File Upload (ManyToOne)

```php
$builder->add('profileImage', DropzoneType::class, [
    'class' => ProfileImage::class,
    'multiple' => false,  // Single file mode
    'maxFiles' => 1,
    'uploadHandler' => 'app_upload_image',
    'removeHandler' => 'app_remove_image',
]);
```

### Image-Only with Custom Dimensions

```php
$builder->add('photos', DropzoneType::class, [
    'class' => Photo::class,
    'acceptedFiles' => 'image/*',
    'maxFiles' => 5,
    'uploadHandler' => 'app_upload_photo',
    'removeHandler' => 'app_remove_photo',
    'thumbnailWidth' => 200,
    'thumbnailHeight' => 200,
    'thumbnailMethod' => 'contain',
    'resizeWidth' => 1920,
    'resizeHeight' => 1080,
    'resizeMethod' => 'contain',
    'resizeMimeType' => 'image/jpeg',
]);
```

### With Custom Headers (API Authentication)

```php
$builder->add('documents', DropzoneType::class, [
    'class' => Document::class,
    'uploadHandler' => 'api_upload_document',
    'removeHandler' => 'api_remove_document',
    'headers' => [
        'Authorization' => 'Bearer ' . $this->apiToken,
    ],
    'formData' => [
        'documentType' => 'invoice',
    ],
]);
```

### Custom Preview Container

```php
{# In template #}
<div id="my-previews"></div>

{{ form_start(form) }}
    {{ form_widget(form.documents) }}
{{ form_end(form) }}

{# In form builder #}
$builder->add('documents', DropzoneType::class, [
    'class' => Document::class,
    'uploadHandler' => 'app_upload_document',
    'removeHandler' => 'app_remove_document',
    'previewsContainer' => '#my-previews',
]);
```

### With Custom Form Data

```php
$builder->add('uploads', DropzoneType::class, [
    'class' => Upload::class,
    'uploadHandler' => 'app_upload_file',
    'removeHandler' => 'app_remove_file',
    'formData' => [
        'category' => 'documents',
        'userId' => $this->currentUser->getId(),
    ],
]);
```

Your upload handler receives this in `$request->request->all()`:

```php
public function upload(Request $request, EntityManagerInterface $em): JsonResponse
{
    $category = $request->request->get('category'); // 'documents'
    $userId = $request->request->get('userId');
    $file = $request->files->get('file');
    // ... handle upload
}
```

## Security

### How the widget renders

The widget never builds JavaScript out of your data. Everything the browser
needs, including stored filenames, is serialised to JSON inside an HTML
attribute and read back with `JSON.parse`. The inline `<script>` is a constant
block of code: it is byte for byte identical whatever your database contains.

This matters because filenames are supplied by whoever uploads a file. Versions
up to and including 2.0.0 interpolated them straight into the script, where
Twig's HTML escaping does not protect a JavaScript string context. See
[SECURITY.md](SECURITY.md) for the details and for how to report an issue.

If you maintain a copy of `dropzone.html.twig` in your own
`templates/bundles/SymfonyDropzoneBundle/`, that copy is what Symfony renders,
and it is still on the old behaviour. Delete it or port your changes over.

### What is still your responsibility

- `maxFilesize` and `acceptedFiles` are checked in the browser only. Enforce the
  real limits in your upload handler.
- Your upload and remove routes need their own access control. The bundle calls
  them, it does not protect them.
- The remove endpoint receives an id straight from the page. Check that the
  current user is allowed to delete that particular file.
- Values you pass through `formData` and `headers` are sent to your own upload
  route, so do not put anything there you would not put in a form field.

## Upgrading from 2.0

No option was renamed or removed, and no application code has to change.

- The generated markup gained a `<span hidden data-dropzone-config="...">` next
  to the widget. If you have CSS or JavaScript selecting on sibling position
  around the dropzone, check it.
- Options are now type checked. A value that used to be coerced, `'5'` instead
  of `5` for `maxFiles` for instance, now raises an exception at form build time.
- Custom behaviour that relied on editing the generated script should move to
  the events described above.

## Upgrading from v1

If you're upgrading from the original `emr-dev/symfony-dropzone`:

- **Bundle namespace changed**: `Ethsam\SymfonyDropzone` (was `EmrDev\SymfonyDropzoneBundle`)
- **Form type import**: Update: `use Ethsam\SymfonyDropzone\Form\DropzoneType;`
- **Option names**: No changes; all options are backward compatible
- **PHP requirement**: Now requires PHP ≥8.1
- **Symfony support**: Now supports Symfony 5.4, 6.x, 7.x
- **Data transformer**: Automatic; no manual entity conversion needed

Migration example:

```php
// Before (v1)
use EmrDev\SymfonyDropzoneBundle\Form\DropzoneType;

// After (v2)
use Ethsam\SymfonyDropzone\Form\DropzoneType;
```

The API and functionality remain the same.

## Differences from symfony/ux-dropzone

| Feature | ethsam/symfony-dropzone | symfony/ux-dropzone |
|---------|------------------------|-------------------|
| **Entity relationships** | Full OneToMany/ManyToOne support | None; form values only |
| **Data transformation** | Automatic ID → Entity | Manual |
| **Multiple files** | Built-in with CollectionType | Not ideal |
| **Edit mode pre-population** | Yes; shows existing files | Manual templating |
| **Upload handler** | Simple route + JSON response | Requires UX component |
| **File removal** | Built-in DELETE handler | Manual |
| **Dropzone config** | Full access to all options | Limited |
| **Learning curve** | Minimal; standard Symfony forms | Moderate; UX paradigm |
| **Maintenance** | Active | Official but UX-focused |

**Summary**: Use `ethsam/symfony-dropzone` for entity-driven file management; use `symfony/ux-dropzone` if you need tight Stimulus integration or prefer the UX paradigm.

## Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/your-feature`
3. Commit your changes: `git commit -m "feat: add your feature"`
4. Push to the branch: `git push origin feat/your-feature`
5. Open a Pull Request

For bug reports or feature requests, please [open an issue](https://github.com/ethsam/symfony-dropzone/issues).

## License

This bundle is licensed under the MIT License. See [LICENSE](LICENSE) for details.

Originally forked from [emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone).

## Credits

- **Samuel Etheve**: Current maintainer
- **Emomaliev M.**: Original author ([emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone))
- **[Dropzone.js](https://www.dropzonejs.com/)**: File upload library
