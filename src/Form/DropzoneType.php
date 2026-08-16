<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Form;

use Doctrine\ORM\EntityManagerInterface;
use Ethsam\SymfonyDropzone\Transformer\DropzoneTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DropzoneType extends AbstractType
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    final public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (false === $options['multiple']) {
            $builder->add(
                'dropzone',
                EntityType::class,
                [
                    'class' => $options['class'],
                    'label' => false,
                    'required' => $options['required'],
                    'attr' => ['style' => 'display: none;']
                ]
            );
        } else {
            $builder->add(
                'dropzone',
                CollectionType::class,
                [
                    'entry_type' => HiddenType::class,
                    'label' => false,
                    'allow_add' => true,
                    'allow_delete' => true
                ]
            );
        }

        $builder->addModelTransformer(new DropzoneTransformer($this->entityManager, $options));

        parent::buildForm($builder, $options);
    }

    final public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['class', 'uploadHandler', 'removeHandler']);

        $resolver->setDefaults([
            'compound' => true,
            'required' => true,
            'choice_src' => 'src',
            'multiple' => true,
            'maxFiles' => 1,
            'maxFilesize' => null,
            'uploadHandlerMethod' => 'POST',
            'removeHandlerMethod' => 'DELETE',
            'withCredentials' => 0,
            'thumbnailWidth' => 120,
            'thumbnailHeight' => 120,
            'thumbnailMethod' => 'crop',
            'resizeWidth' => null,
            'resizeHeight' => null,
            'resizeMimeType' => null,
            'resizeMethod' => 'contain',
            'filesizeBase' => 1024,
            'headers' => [],
            'formData' => [],
            'ignoreHiddenFiles' => true,
            'acceptedFiles' => null,
            'autoProcessQueue' => true,
            'autoQueue' => true,
            'addRemoveLinks' => true,
            'previewsContainer' => null,
        ]);

        // Every option below ends up in the JSON payload the browser reads, so each
        // one is pinned to a type. An unexpected type is refused at build time
        // rather than silently serialised into the page.
        $resolver->setAllowedTypes('class', 'string');
        $resolver->setAllowedTypes('uploadHandler', 'string');
        $resolver->setAllowedTypes('removeHandler', 'string');
        $resolver->setAllowedTypes('choice_src', 'string');
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('maxFiles', ['null', 'int']);
        $resolver->setAllowedTypes('maxFilesize', ['null', 'int', 'float']);
        $resolver->setAllowedTypes('uploadHandlerMethod', 'string');
        $resolver->setAllowedTypes('removeHandlerMethod', 'string');
        $resolver->setAllowedTypes('withCredentials', ['bool', 'int']);
        $resolver->setAllowedTypes('thumbnailWidth', ['null', 'int']);
        $resolver->setAllowedTypes('thumbnailHeight', ['null', 'int']);
        $resolver->setAllowedTypes('thumbnailMethod', 'string');
        $resolver->setAllowedTypes('resizeWidth', ['null', 'int']);
        $resolver->setAllowedTypes('resizeHeight', ['null', 'int']);
        $resolver->setAllowedTypes('resizeMimeType', ['null', 'string']);
        $resolver->setAllowedTypes('resizeMethod', 'string');
        $resolver->setAllowedTypes('filesizeBase', 'int');
        $resolver->setAllowedTypes('headers', 'string[]');
        $resolver->setAllowedTypes('formData', 'scalar[]');
        $resolver->setAllowedTypes('ignoreHiddenFiles', 'bool');
        $resolver->setAllowedTypes('acceptedFiles', ['null', 'string']);
        $resolver->setAllowedTypes('autoProcessQueue', 'bool');
        $resolver->setAllowedTypes('autoQueue', 'bool');
        $resolver->setAllowedTypes('addRemoveLinks', 'bool');
        $resolver->setAllowedTypes('previewsContainer', ['null', 'string']);

        parent::configureOptions($resolver);
    }

    final public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var FormView $f */
        $f = $view->vars['form'];

        $view->vars['formName'] = $f->parent->vars['name'];
        $view->vars['id'] = $this->dashesToCamelCase($f->vars['id']);
        $view->vars['name'] = $f->vars['name'];
        $view->vars['uploadHandler'] = $options['uploadHandler'];
        $view->vars['removeHandler'] = $options['removeHandler'];

        $view->vars['files'] = null;

        if (false === $options['multiple'] && $file = $form->getData()) {
            $view->vars['files'][] = $file;
        } elseif ($form->getData()) {
            $view->vars['files'] = $form->getData();
        }

        $view->vars['class'] = $options['class'];
        $view->vars['required'] = $options['required'];
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['maxFiles'] = $options['maxFiles'];
        $view->vars['uploadHandlerMethod'] = $options['uploadHandlerMethod'];
        $view->vars['removeHandlerMethod'] = $options['removeHandlerMethod'];
        $view->vars['formData'] = $options['formData'];
        $view->vars['choice_src'] = $options['choice_src'];
        $view->vars['withCredentials'] = $options['withCredentials'];
        $view->vars['thumbnailWidth'] = $options['thumbnailWidth'];
        $view->vars['thumbnailHeight'] = $options['thumbnailHeight'];
        $view->vars['thumbnailMethod'] = $options['thumbnailMethod'];
        $view->vars['resizeWidth'] = $options['resizeWidth'];
        $view->vars['resizeHeight'] = $options['resizeHeight'];
        $view->vars['resizeMimeType'] = $options['resizeMimeType'];
        $view->vars['resizeMethod'] = $options['resizeMethod'];
        $view->vars['filesizeBase'] = $options['filesizeBase'];
        $view->vars['headers'] = $options['headers'];
        $view->vars['ignoreHiddenFiles'] = $options['ignoreHiddenFiles'];
        $view->vars['acceptedFiles'] = $options['acceptedFiles'];
        $view->vars['autoProcessQueue'] = $options['autoProcessQueue'];
        $view->vars['autoQueue'] = $options['autoQueue'];
        $view->vars['addRemoveLinks'] = $options['addRemoveLinks'];
        $view->vars['previewsContainer'] = $options['previewsContainer'];
        $view->vars['maxFilesize'] = $options['maxFilesize'];
        $view->vars['dropzoneOptions'] = $this->buildDropzoneOptions($options);
        // Cast so an empty map serialises as {} rather than [], which keeps the
        // browser side free of array/object special cases.
        $view->vars['dropzoneFormData'] = (object) $options['formData'];

        parent::buildView($view, $form, $options);
    }

    /**
     * Builds the option object handed to Dropzone.js.
     *
     * Options left at a falsy value are dropped so Dropzone keeps applying its
     * own defaults, which is the behaviour the widget has always had.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildDropzoneOptions(array $options): array
    {
        $optional = [
            'maxFiles' => $options['maxFiles'],
            'maxFilesize' => $options['maxFilesize'],
            'method' => $options['uploadHandlerMethod'],
            'withCredentials' => $options['withCredentials'],
            'thumbnailWidth' => $options['thumbnailWidth'],
            'thumbnailHeight' => $options['thumbnailHeight'],
            'thumbnailMethod' => $options['thumbnailMethod'],
            'resizeWidth' => $options['resizeWidth'],
            'resizeHeight' => $options['resizeHeight'],
            'resizeMimeType' => $options['resizeMimeType'],
            'resizeMethod' => $options['resizeMethod'],
            'filesizeBase' => $options['filesizeBase'],
            'acceptedFiles' => $options['acceptedFiles'],
            'previewsContainer' => $options['previewsContainer'],
        ];

        $dropzoneOptions = array_filter($optional, static fn (mixed $value): bool => (bool) $value);

        $dropzoneOptions['headers'] = (object) $options['headers'];
        $dropzoneOptions['ignoreHiddenFiles'] = $options['ignoreHiddenFiles'];
        $dropzoneOptions['autoProcessQueue'] = $options['autoProcessQueue'];
        $dropzoneOptions['autoQueue'] = $options['autoQueue'];
        $dropzoneOptions['addRemoveLinks'] = $options['addRemoveLinks'];

        return $dropzoneOptions;
    }

    private function dashesToCamelCase(string $string, bool $capitalizeFirstCharacter = false): string
    {
        $str = str_replace('_', '', ucwords($string, '_'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }
}
