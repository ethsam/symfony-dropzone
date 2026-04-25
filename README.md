# Symfony Dropzone Bundle

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ethsam/symfony-dropzone.svg)](https://packagist.org/packages/ethsam/symfony-dropzone)
[![License](https://img.shields.io/packagist/l/ethsam/symfony-dropzone.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/ethsam/symfony-dropzone.svg)](composer.json)

> Seamless integration of Dropzone.js into Symfony Forms with automatic entity relationship management for drag-and-drop file uploads.

**[EN](README.md) | [FR](docs/README.fr.md) | [ES](docs/README.es.md)**

## Features

- **Drag-and-drop file uploads** — Powered by Dropzone.js
- **Entity relationship support** — Automatically manage OneToMany and ManyToOne associations
- **Built-in data transformation** — IDs to entities via Doctrine ORM
- **Pre-populated edit forms** — Show existing files in edit mode
- **Fully configurable** — Dropzone.js options exposed in form builder
- **Single or multiple files** — Control upload mode per form field
- **Custom upload/remove handlers** — Route-based endpoints with JSON responses
- **Image resizing** — Client-side image processing before upload
- **Flexible authentication** — Custom headers for API integration
- **Symfony Flex compatible** — Automatic bundle registration

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

class FileController extends AbstractController
{
    #[Route('/upload', name: 'app_upload_file', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        // Move the file to your uploads directory
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

## File Entity Requirements

Your file/attachment entity must implement:

- **`getId(): ?int`** — Returns the unique identifier
- **`getFilename(): string`** — Returns the filename for display
- **Getter for `choice_src` property** — By default `getSrc(): string`, returns the file URL/path for thumbnail display

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

1. **Form Type Registration** — `DropzoneType` extends Symfony's form system
2. **Hidden Fields** — For multiple files: a `CollectionType` with hidden inputs; for single: an `EntityType` field
3. **Twig Template** — Renders Dropzone.js widget with JavaScript configuration
4. **Upload Flow**:
   - User drags files or clicks to select
   - Dropzone.js sends each file to your `uploadHandler` route via AJAX
   - Handler persists entity to database, returns `{"id": <int>}`
   - Bundle stores file ID in hidden form field
5. **Form Submission** — Hidden field values are collected
6. **Data Transformation** — `DropzoneTransformer` converts IDs back to entity objects via Doctrine
7. **Persistence** — Form submission handles OneToMany/ManyToOne relationships automatically

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

## Upgrading from v1

If you're upgrading from the original `emr-dev/symfony-dropzone`:

- **Bundle namespace changed** — `Ethsam\SymfonyDropzone` (was `EmrDev\SymfonyDropzoneBundle`)
- **Form type import** — Update: `use Ethsam\SymfonyDropzone\Form\DropzoneType;`
- **Option names** — No changes; all options are backward compatible
- **PHP requirement** — Now requires PHP ≥8.1
- **Symfony support** — Now supports Symfony 5.4, 6.x, 7.x
- **Data transformer** — Automatic; no manual entity conversion needed

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

- **Samuel Etheve** — Current maintainer
- **Emomaliev M.** — Original author ([emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone))
- **[Dropzone.js](https://www.dropzonejs.com/)** — File upload library
