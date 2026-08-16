# Bundle Symfony Dropzone

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ethsam/symfony-dropzone.svg)](https://packagist.org/packages/ethsam/symfony-dropzone)
[![License](https://img.shields.io/packagist/l/ethsam/symfony-dropzone.svg)](../LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/ethsam/symfony-dropzone.svg)](../composer.json)

> Integración fluida de Dropzone.js en formularios Symfony con gestión automática de relaciones de entidades para cargas de archivos de arrastrar y soltar.

**[EN](../README.md) | [FR](README.fr.md) | [ES](README.es.md)**

## Características

- **Carga de archivos de arrastrar y soltar**: Impulsado por Dropzone.js
- **Soporte de relaciones de entidades**: Gestión automática de asociaciones OneToMany y ManyToOne
- **Transformación de datos integrada**: Conversión de IDs a entidades a través de Doctrine ORM
- **Formularios de edición pre-rellenados**: Muestra archivos existentes en modo de edición
- **Completamente configurable**: Las opciones de Dropzone.js se exponen en el generador de formularios
- **Archivos únicos o múltiples**: Controle el modo de carga por campo de formulario
- **Controladores personalizados**: Puntos finales basados en rutas con respuestas JSON
- **Redimensionamiento de imágenes**: Procesamiento del lado del cliente antes de la carga
- **Autenticación flexible**: Encabezados personalizados para integración de API
- **Compatible con Symfony Flex**: Registro automático de bundle

## Requisitos

- **PHP**: ≥8.1
- **Symfony**: 5.4, 6.x, 7.x
- **Doctrine ORM**: 2.12+
- **Dropzone.js**: 6.0+ (incluido vía CDN)

## Instalación

### Paso 1: Instalar vía Composer

```bash
composer require ethsam/symfony-dropzone
```

El bundle se registra automáticamente con Symfony Flex. Si no estás usando Flex, agrega a `config/bundles.php`:

```php
Ethsam\SymfonyDropzone\SymfonyDropzoneBundle::class => ['all' => true],
```

### Paso 2: Incluir Dropzone.js

Agrega lo siguiente a tu plantilla base (por ejemplo, `base.html.twig`):

```html
<link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet" type="text/css" />
<script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
```

¡Eso es! Estás listo para usar `DropzoneType` en tus formularios.

## Inicio rápido

### 1. Define tu entidad de archivo

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
    private string $src = ''; // URL o ruta al archivo

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

### 2. Define tu entidad principal con relación

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

    // Relación OneToMany
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

### 3. Crea un tipo de formulario

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
                'label' => 'Título del artículo',
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

### 4. Crea los controladores de carga/eliminación

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
    // Estas dos rutas las llama el widget, que no las protege.
    // Añade tu propio control de acceso y valida la carga en el servidor: las
    // opciones acceptedFiles y maxFilesize solo filtran en el navegador.
    #[IsGranted('ROLE_USER')]
    #[Route('/upload', name: 'app_upload_file', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No se proporcionó archivo'], 400);
        }

        // Nunca construyas el nombre almacenado a partir del nombre del cliente
        // ni confíes en el tipo MIME anunciado. guessExtension() lee el contenido
        // real del archivo.
        $filename = uniqid() . '.' . $uploadedFile->guessExtension();
        $uploadedFile->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads',
            $filename
        );

        // Crea y persiste el adjunto
        $attachment = new Attachment();
        $attachment->setFilename($uploadedFile->getClientOriginalName());
        $attachment->setSrc('/uploads/' . $filename);

        $em->persist($attachment);
        $em->flush();

        return new JsonResponse(['id' => $attachment->getId()]);
    }

    // El identificador viene directamente de la página: comprueba que este
    // usuario puede borrar ese archivo concreto. Sin esa comprobación cualquier
    // visitante autenticado borra archivos ajenos cambiando el id.
    #[IsGranted('DELETE', subject: 'attachment')]
    #[Route('/remove/{id}', name: 'app_remove_file', methods: ['DELETE'])]
    public function remove(Attachment $attachment, EntityManagerInterface $em): JsonResponse
    {
        $id = $attachment->getId();

        // Opcionalmente, elimina el archivo del disco
        // unlink($this->getParameter('kernel.project_dir') . '/public' . $attachment->getSrc());

        $em->remove($attachment);
        $em->flush();

        return new JsonResponse(['id' => $id]);
    }
}
```

### 5. Usa el formulario en tu plantilla

```twig
{# templates/post/create.html.twig #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>Crear artículo</h1>

    {{ form_start(form) }}
        {{ form_widget(form.title) }}
        {{ form_widget(form.attachments) }}
        <button type="submit">Crear</button>
    {{ form_end(form) }}
{% endblock %}
```

¡Eso es! El bundle maneja todo:
- Renderización del widget Dropzone.js
- Carga de archivos vía AJAX
- Almacenamiento de ID de archivo en campos ocultos
- Transformación de relaciones de entidades en la presentación del formulario

## Opciones de configuración

| Opción | Tipo | Predeterminado | Descripción |
|--------|------|---|-------------|
| `class` | string | null | **Requerido.** Clase de entidad para objetos de archivo/adjunto |
| `multiple` | bool | true | Habilitar modo de archivo múltiple; establece `false` para un único archivo (ManyToOne) |
| `maxFiles` | int | 1 | Número máximo de archivos permitidos en la dropzone |
| `maxFilesize` | int\|float\|null | null | Tamaño máximo de archivo en MiB, rechazado en el navegador antes de la carga. `null` mantiene el valor predeterminado de Dropzone. Es una comodidad para el usuario, no un control de seguridad: aplica siempre el límite de nuevo en tu controlador de carga |
| `uploadHandler` | string | null | **Requerido.** Nombre de ruta Symfony para el punto final de carga |
| `removeHandler` | string | null | **Requerido.** Nombre de ruta Symfony para el punto final de eliminación |
| `uploadHandlerMethod` | string | "POST" | Método HTTP para solicitudes de carga |
| `removeHandlerMethod` | string | "DELETE" | Método HTTP para solicitudes de eliminación |
| `choice_src` | string | "src" | Nombre de propiedad de entidad que contiene URL/ruta del archivo (método getter: `get{PropertyName}()`) |
| `acceptedFiles` | string | null | Tipos MIME aceptados (por ejemplo, `"image/*,.pdf"`) |
| `addRemoveLinks` | bool | true | Mostrar enlace "Eliminar" en vistas previas de archivos |
| `headers` | array | [] | Encabezados HTTP personalizados enviados con solicitudes (por ejemplo, `['Authorization' => 'Bearer TOKEN']`) |
| `formData` | array | [] | Datos de formulario adicionales enviados con solicitud de carga |
| `withCredentials` | int | 0 | Configuración XHR `withCredentials` (0 o 1) |
| `thumbnailWidth` | int | 120 | Ancho de miniaturas de vista previa en píxeles |
| `thumbnailHeight` | int | 120 | Altura de miniaturas de vista previa en píxeles |
| `thumbnailMethod` | string | "crop" | Escalado de miniaturas: `"crop"` o `"contain"` |
| `resizeWidth` | int | null | Ancho de redimensionamiento del lado del cliente antes de carga (conserva relación de aspecto si solo se establece uno) |
| `resizeHeight` | int | null | Altura de redimensionamiento del lado del cliente antes de carga |
| `resizeMimeType` | string | null | Tipo MIME de salida después del redimensionamiento (por ejemplo, `"image/jpeg"`) |
| `resizeMethod` | string | "contain" | Escalado de redimensionamiento: `"crop"` o `"contain"` |
| `filesizeBase` | int | 1024 | Unidad base para cálculos de tamaño de archivo |
| `ignoreHiddenFiles` | bool | true | Ignorar archivos ocultos en directorios |
| `autoProcessQueue` | bool | true | Procesar automáticamente la cola de carga al agregar archivos |
| `autoQueue` | bool | true | Encolar automáticamente archivos cuando se agregan a la dropzone |
| `previewsContainer` | string | null | Selector CSS para contenedor de vista previa personalizado (por ejemplo, `"#my-previews"`) |
| `required` | bool | true | El campo es obligatorio para la validación de formulario |

Cada opción se comprueba por tipo al construir el formulario. Pasar un valor del
tipo equivocado lanza una `InvalidOptionsException` en lugar de escribirse en la
página.

## Eventos JavaScript

El widget emite eventos DOM sobre el elemento dropzone, lo que permite ampliar su
comportamiento sin sobrescribir la plantilla ni inyectar JavaScript desde PHP.
Los eventos se propagan, así que basta con escuchar en `document`.

| Evento | Se emite cuando | `event.detail` |
|--------|-----------------|----------------|
| `symfony-dropzone:init` | La instancia de Dropzone está lista | `{ dropzone, config }` |
| `symfony-dropzone:sending` | Justo antes de enviar un archivo | `{ dropzone, file, xhr, formData }` |
| `symfony-dropzone:removedfile` | Tras eliminar un archivo | `{ file }` |

El prefijo `symfony-dropzone:` no es arbitrario. Dropzone.js 6 emite sus propios
eventos DOM llamados `dropzone:sending`, `dropzone:success` y demás, con un
`detail` de la forma `{args: [...]}`. Siguen disponibles y sin cambios; los de
arriba son los del bundle, con un detail con nombres, y funcionan igual en
Dropzone 5, que no tiene ningún evento DOM.

Añadir a cada carga un valor calculado en el navegador:

```html
<script>
    var uuid = crypto.randomUUID();

    document.addEventListener('symfony-dropzone:sending', function (event) {
        event.detail.formData.append('uuid', uuid);
    });
</script>
```

Acceder a la propia instancia de Dropzone, para todo lo que las opciones no
cubren:

```html
<script>
    document.addEventListener('symfony-dropzone:init', function (event) {
        event.detail.dropzone.on('error', function (file, message) {
            console.warn('carga fallida', file.name, message);
        });
    });
</script>
```

El escuchador de `symfony-dropzone:init` debe registrarse antes de que se renderice el
widget, normalmente en el head de la página o en un script cargado con `defer`.
Los demás eventos se disparan con el uso y pueden asociarse en cualquier momento.

## Requisitos de entidades de archivo

Tu entidad de archivo/adjunto debe implementar:

- **`getId(): ?int`**: Retorna el identificador único
- **`getFilename(): string`**: Retorna el nombre del archivo para mostrar
- **Getter para propiedad `choice_src`**: Por defecto `getSrc(): string`, retorna la URL/ruta del archivo para mostrar miniaturas

Ejemplo de entidad mínima:

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

## Cómo funciona

### Descripción general de la arquitectura

1. **Registro del tipo de formulario**: `DropzoneType` extiende el sistema de formularios de Symfony
2. **Campos ocultos**: Para múltiples archivos: un `CollectionType` con entradas ocultas; para uno: un campo `EntityType`
3. **Plantilla Twig**: Renderiza el widget Dropzone.js con configuración JavaScript
4. **Flujo de carga**:
   - El usuario arrastra archivos o hace clic para seleccionar
   - Dropzone.js envía cada archivo a tu ruta `uploadHandler` vía AJAX
   - El controlador persiste la entidad a la base de datos, retorna `{"id": <int>}`
   - El bundle almacena el ID del archivo en un campo de formulario oculto
5. **Envío de formulario**: Se recopilan los valores de campos ocultos
6. **Transformación de datos**: `DropzoneTransformer` convierte IDs en objetos de entidad vía Doctrine
7. **Persistencia**: El envío del formulario maneja automáticamente las relaciones OneToMany/ManyToOne

### Flujo de eliminación de archivo

1. El usuario hace clic en el enlace "Eliminar" en la vista previa del archivo
2. Dropzone.js envía DELETE (o POST) a la ruta `removeHandler`
3. El controlador elimina la entidad, retorna `{"id": <int>}`
4. El widget elimina la vista previa del DOM
5. En el próximo envío de formulario, el ID eliminado no se incluye, la relación se actualiza

## Ejemplos

### Carga múltiple básica

```php
$builder->add('attachments', DropzoneType::class, [
    'class' => Attachment::class,
    'multiple' => true,
    'maxFiles' => 10,
    'maxFilesize' => 50, // MiB, rechazado en el navegador antes de la carga
    'uploadHandler' => 'app_upload_file',
    'removeHandler' => 'app_remove_file',
]);
```

### Carga de archivo único (ManyToOne)

```php
$builder->add('profileImage', DropzoneType::class, [
    'class' => ProfileImage::class,
    'multiple' => false,  // Modo de archivo único
    'maxFiles' => 1,
    'uploadHandler' => 'app_upload_image',
    'removeHandler' => 'app_remove_image',
]);
```

### Solo imágenes con dimensiones personalizadas

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

### Con encabezados personalizados (autenticación API)

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

### Contenedor de vista previa personalizado

```twig
{# En la plantilla #}
<div id="my-previews"></div>

{{ form_start(form) }}
    {{ form_widget(form.documents) }}
{{ form_end(form) }}

{# En el generador de formulario #}
```

```php
$builder->add('documents', DropzoneType::class, [
    'class' => Document::class,
    'uploadHandler' => 'app_upload_document',
    'removeHandler' => 'app_remove_document',
    'previewsContainer' => '#my-previews',
]);
```

### Con datos de formulario personalizado

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

Tu controlador de carga recibe esto en `$request->request->all()`:

```php
public function upload(Request $request, EntityManagerInterface $em): JsonResponse
{
    $category = $request->request->get('category'); // 'documents'
    $userId = $request->request->get('userId');
    $file = $request->files->get('file');
    // ... manejar carga
}
```

## Seguridad

### Cómo se renderiza el widget

El widget nunca construye JavaScript a partir de tus datos. Todo lo que el
navegador necesita, incluidos los nombres de archivo almacenados, se serializa a
JSON dentro de un atributo HTML y se vuelve a leer con `JSON.parse`. El bloque
`<script>` en línea es un bloque de código constante: es idéntico byte a byte sea
cual sea el contenido de tu base de datos.

Esto importa porque los nombres de archivo los aporta quien sube el archivo. Las
versiones hasta la 2.0.0 incluida los interpolaban directamente en el script,
donde el escapado HTML de Twig no protege un contexto de cadena JavaScript.
Consulta [SECURITY.md](../SECURITY.md) para el detalle y para informar de un
problema.

Si mantienes una copia de `dropzone.html.twig` en tu
`templates/bundles/SymfonyDropzoneBundle/`, esa copia es la que Symfony renderiza
y conserva el comportamiento antiguo. Elimínala o traslada tus cambios.

### Lo que sigue siendo responsabilidad tuya

- `maxFilesize` y `acceptedFiles` solo se comprueban en el navegador. Aplica los
  límites reales en tu controlador de carga.
- Tus rutas de carga y eliminación necesitan su propio control de acceso. El
  bundle las llama, no las protege.
- El punto de eliminación recibe un identificador que viene directamente de la
  página. Comprueba que el usuario actual tiene derecho a borrar ese archivo
  concreto.
- Los valores que pasas por `formData` y `headers` viajan a tu propia ruta de
  carga: no pongas ahí nada que no pondrías en un campo de formulario.

## Actualización desde 2.0

Ninguna opción se ha renombrado ni eliminado, y ningún código de aplicación
necesita cambiar.

- El marcado generado incluye ahora un `<span hidden data-dropzone-config="...">`
  junto al widget. Si tienes CSS o JavaScript que selecciona por posición de
  hermano alrededor de la dropzone, revísalo.
- Las opciones se comprueban por tipo. Un valor que antes se convertía en
  silencio, `'5'` en lugar de `5` para `maxFiles` por ejemplo, ahora lanza una
  excepción al construir el formulario.
- Los comportamientos personalizados que dependían de editar el script generado
  deben pasar a los eventos descritos arriba.

## Actualización desde v1

Si estás actualizando desde el original `emr-dev/symfony-dropzone`:

- **El espacio de nombres del bundle cambió**: `Ethsam\SymfonyDropzone` (era `EmrDev\SymfonyDropzoneBundle`)
- **Importación de tipo de formulario**: Actualiza: `use Ethsam\SymfonyDropzone\Form\DropzoneType;`
- **Nombres de opciones**: Sin cambios; todas las opciones son retrocompatibles
- **Requisito PHP**: Ahora requiere PHP ≥8.1
- **Soporte Symfony**: Ahora soporta Symfony 5.4, 6.x, 7.x
- **Transformador de datos**: Automático; sin conversión manual de entidades necesaria

Ejemplo de migración:

```php
// Antes (v1)
use EmrDev\SymfonyDropzoneBundle\Form\DropzoneType;

// Después (v2)
use Ethsam\SymfonyDropzone\Form\DropzoneType;
```

La API y funcionalidad permanecen igual.

## Diferencias con symfony/ux-dropzone

| Característica | ethsam/symfony-dropzone | symfony/ux-dropzone |
|---|---|---|
| **Relaciones de entidades** | Soporte completo OneToMany/ManyToOne | Ninguno; solo valores de formulario |
| **Transformación de datos** | ID → Entidad automático | Manual |
| **Archivos múltiples** | Integrado con CollectionType | No ideal |
| **Pre-rellenado en modo edición** | Sí; muestra archivos existentes | Plantillas manuales |
| **Controlador de carga** | Ruta simple + respuesta JSON | Requiere componente UX |
| **Eliminación de archivo** | Controlador DELETE integrado | Manual |
| **Configuración de Dropzone** | Acceso completo a todas las opciones | Limitado |
| **Curva de aprendizaje** | Mínima; formularios estándar de Symfony | Moderada; paradigma UX |
| **Mantenimiento** | Activo | Oficial pero enfocado en UX |

**Resumen**: Usa `ethsam/symfony-dropzone` para gestión de archivos orientada a entidades; usa `symfony/ux-dropzone` si necesitas integración cerada con Stimulus o prefieres el paradigma UX.

## Contribución

¡Aceptamos contribuciones! Por favor:

1. Haz fork del repositorio
2. Crea una rama de característica: `git checkout -b feat/your-feature`
3. Realiza commits de tus cambios: `git commit -m "feat: add your feature"`
4. Sube a la rama: `git push origin feat/your-feature`
5. Abre un Pull Request

Para reportes de bugs o solicitudes de características, por favor [abre una incidencia](https://github.com/ethsam/symfony-dropzone/issues).

## Licencia

Este bundle está licenciado bajo la Licencia MIT. Ver [LICENSE](../LICENSE) para más detalles.

Originalmente bifurcado desde [emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone).

## Créditos

- **Samuel Etheve**: Responsable actual
- **Emomaliev M.**: Autor original ([emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone))
- **[Nico Hiort af Ornäs](https://github.com/nicodemuz)**: opción `maxFilesize`, y los informes que llevaron a la revisión de seguridad de la 2.1.0
- **[Dropzone.js](https://www.dropzonejs.com/)**: Biblioteca de carga de archivos
