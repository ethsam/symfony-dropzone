# Bundle Symfony Dropzone

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ethsam/symfony-dropzone.svg)](https://packagist.org/packages/ethsam/symfony-dropzone)
[![License](https://img.shields.io/packagist/l/ethsam/symfony-dropzone.svg)](../LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/ethsam/symfony-dropzone.svg)](../composer.json)

> Intégration fluide de Dropzone.js dans les formulaires Symfony avec gestion automatique des relations d'entités pour les téléchargements de fichiers en glisser-déposer.

**[EN](../README.md) | [FR](README.fr.md) | [ES](README.es.md)**

## Fonctionnalités

- **Téléchargement glisser-déposer**: Alimenté par Dropzone.js
- **Support des relations d'entités**: Gestion automatique des associations OneToMany et ManyToOne
- **Transformation de données intégrée**: Conversion des IDs en entités via Doctrine ORM
- **Formulaires de modification pré-remplis**: Affiche les fichiers existants en mode édition
- **Entièrement configurable**: Les options de Dropzone.js sont exposées dans le générateur de formulaire
- **Fichiers uniques ou multiples**: Contrôlez le mode de téléchargement par champ de formulaire
- **Gestionnaires personnalisés**: Points de terminaison basés sur des routes avec réponses JSON
- **Redimensionnement d'images**: Traitement côté client avant le téléchargement
- **Authentification flexible**: En-têtes personnalisés pour l'intégration API
- **Compatible Symfony Flex**: Enregistrement de bundle automatique

## Prérequis

- **PHP** : ≥8.1
- **Symfony** : 5.4, 6.x, 7.x
- **Doctrine ORM** : 2.12+
- **Dropzone.js** : 6.0+ (inclus via CDN)

## Installation

### Étape 1 : Installez via Composer

```bash
composer require ethsam/symfony-dropzone
```

Le bundle s'enregistre automatiquement avec Symfony Flex. Si vous n'utilisez pas Flex, ajoutez à `config/bundles.php` :

```php
Ethsam\SymfonyDropzone\SymfonyDropzoneBundle::class => ['all' => true],
```

### Étape 2 : Incluez Dropzone.js

Ajoutez ce qui suit à votre modèle de base (par exemple, `base.html.twig`) :

```html
<link href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" rel="stylesheet" type="text/css" />
<script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
```

C'est fait ! Vous êtes prêt à utiliser `DropzoneType` dans vos formulaires.

## Démarrage rapide

### 1. Définissez votre entité de fichier

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
    private string $src = ''; // URL ou chemin vers le fichier

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

### 2. Définissez votre entité principale avec relation

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

    // Relation OneToMany
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

### 3. Créez un type de formulaire

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
                'label' => 'Titre du message',
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

### 4. Créez les gestionnaires de téléchargement/suppression

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
    // Ces deux routes sont appelées par le widget, il ne les protège pas.
    // Ajoutez votre propre contrôle d'accès et validez l'envoi côté serveur :
    // les options acceptedFiles et maxFilesize ne filtrent que dans le navigateur.
    #[IsGranted('ROLE_USER')]
    #[Route('/upload', name: 'app_upload_file', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'Aucun fichier fourni'], 400);
        }

        // Ne construisez jamais le nom stocké à partir de celui du client et ne
        // faites jamais confiance au type MIME annoncé. guessExtension() lit le
        // contenu réel du fichier.
        $filename = uniqid() . '.' . $uploadedFile->guessExtension();
        $uploadedFile->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads',
            $filename
        );

        // Créez et persistez la pièce jointe
        $attachment = new Attachment();
        $attachment->setFilename($uploadedFile->getClientOriginalName());
        $attachment->setSrc('/uploads/' . $filename);

        $em->persist($attachment);
        $em->flush();

        return new JsonResponse(['id' => $attachment->getId()]);
    }

    // L'identifiant vient directement de la page : vérifiez que cet utilisateur
    // a le droit de supprimer ce fichier précis. Sans ce contrôle, n'importe quel
    // visiteur authentifié supprime les fichiers des autres en changeant l'id.
    #[IsGranted('DELETE', subject: 'attachment')]
    #[Route('/remove/{id}', name: 'app_remove_file', methods: ['DELETE'])]
    public function remove(Attachment $attachment, EntityManagerInterface $em): JsonResponse
    {
        $id = $attachment->getId();

        // Optionnellement, supprimez le fichier du disque
        // unlink($this->getParameter('kernel.project_dir') . '/public' . $attachment->getSrc());

        $em->remove($attachment);
        $em->flush();

        return new JsonResponse(['id' => $id]);
    }
}
```

### 5. Utilisez le formulaire dans votre modèle

```twig
{# templates/post/create.html.twig #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>Créer un message</h1>

    {{ form_start(form) }}
        {{ form_widget(form.title) }}
        {{ form_widget(form.attachments) }}
        <button type="submit">Créer</button>
    {{ form_end(form) }}
{% endblock %}
```

C'est tout ! Le bundle gère tout :
- Rendu du widget Dropzone.js
- Téléchargement de fichiers via AJAX
- Stockage de l'ID du fichier dans les champs masqués
- Transformation de la relation d'entité lors de la soumission du formulaire

## Options de configuration

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `class` | string | null | **Obligatoire.** Classe d'entité pour les objets de fichier/pièce jointe |
| `multiple` | bool | true | Activer le mode fichier multiple ; définissez `false` pour un seul fichier (ManyToOne) |
| `maxFiles` | int | 1 | Nombre maximum de fichiers autorisés dans la dropzone |
| `maxFilesize` | int\|float\|null | null | Taille maximale d'un fichier en Mio, refusée dans le navigateur avant l'envoi. `null` conserve la valeur par défaut de Dropzone. C'est un confort pour l'utilisateur, pas un contrôle de sécurité : appliquez toujours la limite à nouveau dans votre gestionnaire de téléchargement |
| `uploadHandler` | string | null | **Obligatoire.** Nom de la route Symfony pour le point de terminaison de téléchargement |
| `removeHandler` | string | null | **Obligatoire.** Nom de la route Symfony pour le point de terminaison de suppression |
| `uploadHandlerMethod` | string | "POST" | Méthode HTTP pour les demandes de téléchargement |
| `removeHandlerMethod` | string | "DELETE" | Méthode HTTP pour les demandes de suppression |
| `choice_src` | string | "src" | Nom de la propriété d'entité contenant l'URL/le chemin du fichier (méthode getter : `get{PropertyName}()`) |
| `acceptedFiles` | string | null | Types MIME acceptés (par ex., `"image/*,.pdf"`) |
| `addRemoveLinks` | bool | true | Afficher le lien « Supprimer » sur les aperçus de fichiers |
| `headers` | array | [] | En-têtes HTTP personnalisés envoyés avec les demandes (par ex., `['Authorization' => 'Bearer TOKEN']`) |
| `formData` | array | [] | Données de formulaire supplémentaires envoyées avec la demande de téléchargement |
| `withCredentials` | int | 0 | Paramètre XHR `withCredentials` (0 ou 1) |
| `thumbnailWidth` | int | 120 | Largeur des miniatures d'aperçu en pixels |
| `thumbnailHeight` | int | 120 | Hauteur des miniatures d'aperçu en pixels |
| `thumbnailMethod` | string | "crop" | Mise à l'échelle des miniatures : `"crop"` ou `"contain"` |
| `resizeWidth` | int | null | Largeur de redimensionnement côté client avant le téléchargement (conserve les proportions si un seul est défini) |
| `resizeHeight` | int | null | Hauteur de redimensionnement côté client avant le téléchargement |
| `resizeMimeType` | string | null | Type MIME de sortie après redimensionnement (par ex., `"image/jpeg"`) |
| `resizeMethod` | string | "contain" | Mise à l'échelle du redimensionnement : `"crop"` ou `"contain"` |
| `filesizeBase` | int | 1024 | Unité de base pour les calculs de taille de fichier |
| `ignoreHiddenFiles` | bool | true | Ignorer les fichiers cachés dans les répertoires |
| `autoProcessQueue` | bool | true | Traiter automatiquement la file d'attente de téléchargement lors de l'ajout de fichiers |
| `autoQueue` | bool | true | Mettre en file d'attente automatiquement les fichiers lors de l'ajout à la dropzone |
| `previewsContainer` | string | null | Sélecteur CSS pour un conteneur d'aperçu personnalisé (par ex., `"#my-previews"`) |
| `required` | bool | true | Le champ est obligatoire pour la validation du formulaire |

Chaque option est vérifiée en type à la construction du formulaire. Passer une
valeur du mauvais type lève une `InvalidOptionsException` au lieu d'être écrite
dans la page.

## Événements JavaScript

Le widget émet des événements DOM sur l'élément dropzone, ce qui permet d'étendre
son comportement sans surcharger le gabarit ni injecter du JavaScript depuis PHP.
Les événements remontent, écouter sur `document` suffit donc.

| Événement | Émis quand | `event.detail` |
|-----------|-----------|----------------|
| `symfony-dropzone:init` | L'instance Dropzone est prête | `{ dropzone, config }` |
| `symfony-dropzone:sending` | Juste avant l'envoi d'un fichier | `{ dropzone, file, xhr, formData }` |
| `symfony-dropzone:removedfile` | Après la suppression d'un fichier | `{ file }` |

Le préfixe `symfony-dropzone:` n'est pas un caprice. Dropzone.js 6 émet ses
propres événements DOM nommés `dropzone:sending`, `dropzone:success` et ainsi de
suite, avec un `detail` de la forme `{args: [...]}`. Ils restent disponibles et
inchangés ; ceux ci-dessus sont ceux du bundle, avec un detail nommé, et ils
fonctionnent à l'identique sur Dropzone 5 qui n'a aucun événement DOM.

Ajouter à chaque envoi une valeur calculée dans le navigateur :

```html
<script>
    var uuid = crypto.randomUUID();

    document.addEventListener('symfony-dropzone:sending', function (event) {
        event.detail.formData.append('uuid', uuid);
    });
</script>
```

Atteindre l'instance Dropzone elle-même, pour tout ce que les options ne couvrent
pas :

```html
<script>
    document.addEventListener('symfony-dropzone:init', function (event) {
        event.detail.dropzone.on('error', function (file, message) {
            console.warn('échec du téléchargement', file.name, message);
        });
    });
</script>
```

L'écouteur de `symfony-dropzone:init` doit être enregistré avant le rendu du widget,
typiquement dans le head de la page ou dans un script chargé en `defer`. Les
autres événements se déclenchent à l'usage et peuvent être attachés à tout moment.

## Exigences des entités de fichier

Votre entité de fichier/pièce jointe doit implémenter :

- **`getId(): ?int`**: Retourne l'identifiant unique
- **`getFilename(): string`**: Retourne le nom du fichier pour l'affichage
- **Getter pour la propriété `choice_src`**: Par défaut `getSrc(): string`, retourne l'URL/le chemin du fichier pour l'affichage des miniatures

Exemple d'entité minimale :

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

## Comment ça marche

### Aperçu de l'architecture

1. **Enregistrement du type de formulaire**: `DropzoneType` étend le système de formulaires Symfony
2. **Champs masqués**: Pour plusieurs fichiers : un `CollectionType` avec des entrées masquées ; pour un seul : un champ `EntityType`
3. **Modèle Twig**: Rend le widget Dropzone.js avec la configuration JavaScript
4. **Flux de téléchargement** :
   - L'utilisateur fait glisser des fichiers ou clique pour sélectionner
   - Dropzone.js envoie chaque fichier à votre route `uploadHandler` via AJAX
   - Le gestionnaire persiste l'entité à la base de données, renvoie `{"id": <int>}`
   - Le bundle stocke l'ID du fichier dans un champ de formulaire masqué
5. **Soumission de formulaire**: Les valeurs des champs masqués sont collectées
6. **Transformation des données**: `DropzoneTransformer` convertit les IDs en objets d'entité via Doctrine
7. **Persistance**: La soumission du formulaire gère automatiquement les relations OneToMany/ManyToOne

### Flux de suppression de fichier

1. L'utilisateur clique sur le lien « Supprimer » sur l'aperçu du fichier
2. Dropzone.js envoie DELETE (ou POST) à la route `removeHandler`
3. Le gestionnaire supprime l'entité, renvoie `{"id": <int>}`
4. Le widget supprime l'aperçu du DOM
5. Lors de la prochaine soumission du formulaire, l'ID supprimé n'est pas inclus, la relation est mise à jour

## Exemples

### Téléchargement multiple basique

```php
$builder->add('attachments', DropzoneType::class, [
    'class' => Attachment::class,
    'multiple' => true,
    'maxFiles' => 10,
    'maxFilesize' => 50, // Mio, refusé dans le navigateur avant l'envoi
    'uploadHandler' => 'app_upload_file',
    'removeHandler' => 'app_remove_file',
]);
```

### Téléchargement d'un seul fichier (ManyToOne)

```php
$builder->add('profileImage', DropzoneType::class, [
    'class' => ProfileImage::class,
    'multiple' => false,  // Mode fichier unique
    'maxFiles' => 1,
    'uploadHandler' => 'app_upload_image',
    'removeHandler' => 'app_remove_image',
]);
```

### Images uniquement avec dimensions personnalisées

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

### Avec en-têtes personnalisés (authentification API)

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

### Conteneur d'aperçu personnalisé

```twig
{# Dans le modèle #}
<div id="my-previews"></div>

{{ form_start(form) }}
    {{ form_widget(form.documents) }}
{{ form_end(form) }}

{# Dans le générateur de formulaire #}
```

```php
$builder->add('documents', DropzoneType::class, [
    'class' => Document::class,
    'uploadHandler' => 'app_upload_document',
    'removeHandler' => 'app_remove_document',
    'previewsContainer' => '#my-previews',
]);
```

### Avec données de formulaire personnalisées

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

Votre gestionnaire de téléchargement reçoit cela dans `$request->request->all()` :

```php
public function upload(Request $request, EntityManagerInterface $em): JsonResponse
{
    $category = $request->request->get('category'); // 'documents'
    $userId = $request->request->get('userId');
    $file = $request->files->get('file');
    // ... gérer le téléchargement
}
```

## Sécurité

### Comment le widget est rendu

Le widget ne construit jamais de JavaScript à partir de vos données. Tout ce dont
le navigateur a besoin, y compris les noms de fichiers stockés, est sérialisé en
JSON dans un attribut HTML puis relu avec `JSON.parse`. Le bloc `<script>` en
ligne est un bloc de code constant : il est identique octet pour octet quel que
soit le contenu de votre base.

C'est important parce que les noms de fichiers sont fournis par la personne qui
téléverse. Les versions jusqu'à 2.0.0 incluse les interpolaient directement dans
le script, où l'échappement HTML de Twig ne protège pas un contexte de chaîne
JavaScript. Voir [SECURITY.md](../SECURITY.md) pour le détail et pour signaler un
problème.

Si vous conservez une copie de `dropzone.html.twig` dans votre
`templates/bundles/SymfonyDropzoneBundle/`, c'est cette copie que Symfony rend et
elle conserve l'ancien comportement. Supprimez-la ou reportez-y vos changements.

### Ce qui reste à votre charge

- `maxFilesize` et `acceptedFiles` ne sont vérifiés que dans le navigateur.
  Appliquez les vraies limites dans votre gestionnaire de téléchargement.
- Vos routes d'envoi et de suppression ont besoin de leur propre contrôle
  d'accès. Le bundle les appelle, il ne les protège pas.
- Le point de suppression reçoit un identifiant venu directement de la page.
  Vérifiez que l'utilisateur courant a le droit de supprimer ce fichier précis.
- Les valeurs passées par `formData` et `headers` partent vers votre propre route
  d'envoi : n'y mettez rien que vous ne mettriez pas dans un champ de formulaire.

## Mise à jour depuis la 2.0

Aucune option n'a été renommée ni supprimée, et aucun code applicatif n'a besoin
de changer.

- Le balisage généré comporte désormais un `<span hidden data-dropzone-config="...">`
  à côté du widget. Si du CSS ou du JavaScript sélectionne par position de frère
  autour de la dropzone, vérifiez-le.
- Les options sont maintenant vérifiées en type. Une valeur auparavant convertie
  silencieusement, `'5'` au lieu de `5` pour `maxFiles` par exemple, lève
  désormais une exception à la construction du formulaire.
- Les comportements personnalisés qui reposaient sur une modification du script
  généré doivent passer par les événements décrits plus haut.

## Mise à jour à partir de la v1

Si vous mettez à jour à partir de l'original `emr-dev/symfony-dropzone` :

- **L'espace de noms du bundle a changé**: `Ethsam\SymfonyDropzone` (était `EmrDev\SymfonyDropzoneBundle`)
- **Importation du type de formulaire**: Mettez à jour : `use Ethsam\SymfonyDropzone\Form\DropzoneType;`
- **Noms des options**: Aucune modification ; toutes les options sont rétrocompatibles
- **Exigences PHP**: Nécessite maintenant PHP ≥8.1
- **Support Symfony**: Supporte maintenant Symfony 5.4, 6.x, 7.x
- **Transformateur de données**: Automatique ; aucune conversion d'entité manuelle nécessaire

Exemple de migration :

```php
// Avant (v1)
use EmrDev\SymfonyDropzoneBundle\Form\DropzoneType;

// Après (v2)
use Ethsam\SymfonyDropzone\Form\DropzoneType;
```

L'API et les fonctionnalités restent les mêmes.

## Différences avec symfony/ux-dropzone

| Fonctionnalité | ethsam/symfony-dropzone | symfony/ux-dropzone |
|---|---|---|
| **Relations d'entités** | Support complet OneToMany/ManyToOne | Aucun ; valeurs de formulaire uniquement |
| **Transformation de données** | ID → Entité automatique | Manuel |
| **Fichiers multiples** | Intégré avec CollectionType | Pas idéal |
| **Préremplissage du mode édition** | Oui ; affiche les fichiers existants | Modèles manuels |
| **Gestionnaire de téléchargement** | Route simple + réponse JSON | Nécessite un composant UX |
| **Suppression de fichier** | Gestionnaire DELETE intégré | Manuel |
| **Configuration Dropzone** | Accès complet à toutes les options | Limité |
| **Courbe d'apprentissage** | Minimale ; formulaires Symfony standard | Modérée ; paradigme UX |
| **Maintenance** | Actif | Officiel mais axé sur UX |

**Résumé** : Utilisez `ethsam/symfony-dropzone` pour la gestion des fichiers orientée entité ; utilisez `symfony/ux-dropzone` si vous avez besoin d'une intégration serrée avec Stimulus ou préférez le paradigme UX.

## Contribution

Nous accueillons les contributions ! Veuillez :

1. Forker le référentiel
2. Créer une branche de fonctionnalité : `git checkout -b feat/your-feature`
3. Valider vos modifications : `git commit -m "feat: add your feature"`
4. Pousser vers la branche : `git push origin feat/your-feature`
5. Ouvrir une demande d'extraction

Pour les rapports de bugs ou les demandes de fonctionnalités, veuillez [ouvrir un problème](https://github.com/ethsam/symfony-dropzone/issues).

## Licence

Ce bundle est sous licence MIT. Voir [LICENSE](../LICENSE) pour plus de détails.

Forké à l'origine de [emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone).

## Crédits

- **Samuel Etheve**: Responsable actuel
- **Emomaliev M.**: Auteur original ([emr-dev/symfony-dropzone](https://github.com/emr-dev/symfony-dropzone))
- **[Nico Hiort af Ornäs](https://github.com/nicodemuz)** : option `maxFilesize`, et les signalements qui ont mené à la revue de sécurité de la 2.1.0
- **[Dropzone.js](https://www.dropzonejs.com/)**: Bibliothèque de téléchargement de fichiers
