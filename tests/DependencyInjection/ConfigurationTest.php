<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\DependencyInjection;

use Ethsam\SymfonyDropzone\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function emptyConfigurationIsValid(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);

        $this->assertIsArray($config);
    }

    #[Test]
    public function treeBuilderHasCorrectRootName(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertSame('symfony_dropzone', $treeBuilder->buildTree()->getName());
    }
}
