<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Form;

use Ethsam\SymfonyDropzone\Form\DropzoneType;
use Ethsam\SymfonyDropzone\Tests\Fixtures\DoctrineKit;
use Ethsam\SymfonyDropzone\Tests\Fixtures\StoredFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

/**
 * The single-file mode builds an EntityType instead of a collection of hidden
 * fields, which means a real Doctrine stack and a real ManagerRegistry. It went
 * uncovered until 2.1, and symfony/doctrine-bridge was not even a declared
 * dependency, so this path could not run at all outside a full application.
 */
final class DropzoneSingleModeRenderTest extends TestCase
{
    public function testTheHiddenSelectCarriesTheAttachedFile(): void
    {
        $html = $this->renderSingle([
            ['id' => 7, 'filename' => 'holiday.pdf', 'src' => '/uploads/7.pdf', 'size' => 2048, 'mimetype' => 'application/pdf'],
        ], 7);

        $this->assertMatchesRegularExpression('/<select[^>]*id="formAvatar_dropzone"/', $html);
        $this->assertMatchesRegularExpression('/<option value="7"[^>]*selected/', $html);
    }

    public function testTheConfigDescribesTheSingleMode(): void
    {
        $config = $this->extractConfig($this->renderSingle([], null));

        $this->assertFalse($config['multiple']);
        $this->assertSame('formAvatar_dropzone', $config['widgetId']);
        $this->assertSame([], $config['files']);
    }

    /**
     * Same security contract as the multiple mode: the inline script is a
     * constant, so a stored filename can never be parsed as code.
     */
    #[DataProvider('hostileFilenameProvider')]
    public function testTheInlineScriptStaysConstantWhateverTheStoredName(string $hostileFilename): void
    {
        $benign = $this->renderSingle([
            ['id' => 1, 'filename' => 'holiday.pdf', 'src' => '/uploads/1.pdf'],
        ], 1);

        $hostile = $this->renderSingle([
            ['id' => 1, 'filename' => $hostileFilename, 'src' => '/uploads/1.pdf'],
        ], 1);

        $this->assertSame(
            $this->extractInlineScript($benign),
            $this->extractInlineScript($hostile),
        );

        $this->assertStringNotContainsString('onerror', $this->extractInlineScript($hostile));
    }

    public static function hostileFilenameProvider(): iterable
    {
        yield 'script closing tag' => ['</script><img src=x onerror=alert(1)>.pdf'];
        yield 'trailing backslash' => ['invoice\\'];
        yield 'single quote break out' => ["'; alert(1); var a='.pdf"];
    }

    public function testTheStoredNameSurvivesTheRoundTripThroughTheAttribute(): void
    {
        $config = $this->extractConfig($this->renderSingle([
            ['id' => 3, 'filename' => "O'Brien & Co <report>.pdf", 'src' => '/uploads/3.pdf', 'size' => 4096],
        ], 3));

        $this->assertSame("O'Brien & Co <report>.pdf", $config['files'][0]['name']);
        $this->assertSame(3, $config['files'][0]['id']);
        $this->assertSame(4096, $config['files'][0]['size']);
    }

    /**
     * @param list<array{id: int, filename: string, src: string, size?: int|null, mimetype?: string|null}> $rows
     */
    private function renderSingle(array $rows, ?int $selectedId): string
    {
        $registry = DoctrineKit::registry($rows);
        $entityManager = $registry->getManager();

        $factory = Forms::createFormFactoryBuilder()
            ->addType(new DropzoneType($entityManager))
            ->addType(new EntityType($registry))
            ->getFormFactory();

        $selected = null === $selectedId
            ? null
            : $entityManager->getRepository(StoredFile::class)->find($selectedId);

        $form = $factory->createBuilder(FormType::class, ['avatar' => $selected])
            ->add('avatar', DropzoneType::class, [
                'class' => StoredFile::class,
                'uploadHandler' => 'app_upload',
                'removeHandler' => 'app_remove',
                'multiple' => false,
            ])
            ->getForm();

        return $this->renderer()->searchAndRenderBlock($form->createView()->children['avatar'], 'widget');
    }

    private function renderer(): FormRenderer
    {
        $bridgeViews = \dirname((new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form';

        $twig = new Environment(new FilesystemLoader([
            __DIR__.'/../../src/Resources/views/Form',
            $bridgeViews,
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension());
        $twig->addFunction(new TwigFunction('path', static function (string $route, array $parameters = []): string {
            $path = '/'.str_replace('_', '/', $route);

            foreach ($parameters as $value) {
                $path .= '/'.rawurlencode((string) $value);
            }

            return $path;
        }));

        $engine = new TwigRendererEngine(['form_div_layout.html.twig', 'dropzone.html.twig'], $twig);
        $renderer = new FormRenderer($engine);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => $renderer,
        ]));

        return $renderer;
    }

    private function extractInlineScript(string $html): string
    {
        self::assertSame(1, preg_match('#<script>(.*)</script>#s', $html, $matches), 'Expected exactly one inline script.');

        return $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractConfig(string $html): array
    {
        self::assertSame(1, preg_match('#data-dropzone-config="([^"]*)"#', $html, $matches), 'Expected a JSON config attribute.');

        $decoded = json_decode(html_entity_decode($matches[1], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        return $decoded;
    }
}
