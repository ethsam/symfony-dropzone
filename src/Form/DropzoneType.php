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

        parent::buildView($view, $form, $options);
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
