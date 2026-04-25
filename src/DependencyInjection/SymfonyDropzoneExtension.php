<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SymfonyDropzoneExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('dropzone.php');

        $this->loadTwigTheme($container);
    }

    private function loadTwigTheme(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('twig.form.resources')) {
            return;
        }

        $container->setParameter('twig.form.resources', array_merge(
            ['@SymfonyDropzone/Form/dropzone.html.twig'],
            $container->getParameter('twig.form.resources')
        ));
    }
}
