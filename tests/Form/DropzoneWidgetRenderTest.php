<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Form;

use Doctrine\ORM\EntityManagerInterface;
use Ethsam\SymfonyDropzone\Form\DropzoneType;
use Ethsam\SymfonyDropzone\Tests\Fixtures\FileFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

/**
 * Renders the widget for real and asserts on the generated markup.
 *
 * The security contract these tests lock down: no application data is ever
 * interpolated into the inline <script>. Everything the script needs travels
 * through a JSON payload held in an HTML attribute, where Twig's HTML escaping
 * is the correct and sufficient defence.
 */
final class DropzoneWidgetRenderTest extends TestCase
{
    /**
     * Filenames come from user uploads, so they are attacker-controlled data
     * that the widget renders back into the page.
     */
    public static function hostileFilenameProvider(): iterable
    {
        yield 'script closing tag' => ['</script><img src=x onerror=alert(1)>.pdf'];
        yield 'trailing backslash' => ['invoice\\.pdf'];
        yield 'single quote break out' => ["'; alert(1); var a='.pdf"];
        yield 'line separator' => ["report\u{2028}alert(1)\u{2029}.pdf"];
        yield 'newline' => ["report\n alert(1) \n.pdf"];
        yield 'html entities' => ['O&#039;Brien &amp; Co.pdf'];
    }

    #[DataProvider('hostileFilenameProvider')]
    public function testInlineScriptIsIdenticalWhateverTheStoredData(string $hostileFilename): void
    {
        $benign = $this->renderWidget([
            new FileFixture(1, 'holiday.pdf', '/uploads/holiday.pdf', 1024, 'application/pdf'),
        ]);

        $hostile = $this->renderWidget([
            new FileFixture(1, $hostileFilename, '/uploads/x.pdf', 1024, 'application/pdf'),
        ]);

        $this->assertSame(
            $this->extractInlineScript($benign),
            $this->extractInlineScript($hostile),
            'The inline script must not vary with stored data, otherwise it is an injection sink.',
        );
    }

    public function testHostileFilenameNeverReachesTheScriptBlock(): void
    {
        $html = $this->renderWidget([
            new FileFixture(1, '</script><img src=x onerror=alert(1)>.pdf', '/uploads/x.pdf'),
        ]);

        $script = $this->extractInlineScript($html);

        $this->assertStringNotContainsString('onerror', $script);
        $this->assertStringNotContainsString('<img', $script);
        $this->assertSame(1, substr_count($html, '</script>'), 'The payload must not be able to close the script tag.');
    }

    public function testStoredDataTravelsThroughAnEscapedHtmlAttribute(): void
    {
        $html = $this->renderWidget([
            new FileFixture(7, "O'Brien & Co <report>.pdf", '/uploads/7.pdf', 2048, 'application/pdf'),
        ]);

        $config = $this->extractConfig($html);

        $this->assertSame(7, $config['files'][0]['id']);
        $this->assertSame("O'Brien & Co <report>.pdf", $config['files'][0]['name']);
        $this->assertSame('/uploads/7.pdf', $config['files'][0]['url']);
        $this->assertSame(2048, $config['files'][0]['size']);
        $this->assertSame('application/pdf', $config['files'][0]['type']);
    }

    public function testHostileOptionValuesAreNotInterpolatedEither(): void
    {
        $html = $this->renderWidget([], [
            'acceptedFiles' => "image/*'; alert(1); //",
            'formData' => ['token' => "'); alert(1); //"],
            'headers' => ['X-Custom' => "'); alert(1); //"],
        ]);

        $script = $this->extractInlineScript($html);
        $config = $this->extractConfig($html);

        $this->assertStringNotContainsString('alert(1)', $script);
        $this->assertSame("image/*'; alert(1); //", $config['options']['acceptedFiles']);
        $this->assertSame("'); alert(1); //", $config['formData']['token']);
        $this->assertSame("'); alert(1); //", $config['options']['headers']['X-Custom']);
    }

    public function testWidgetIdMatchesTheRenderedFieldSoTheScriptCanFindIt(): void
    {
        $html = $this->renderWidget([]);
        $config = $this->extractConfig($html);

        $this->assertMatchesRegularExpression(
            '/id="' . preg_quote($config['widgetId'], '/') . '"/',
            $html,
            'The script looks the field up by id, so that id has to exist in the markup.',
        );
    }

    public function testOnlyTruthyOptionsAreForwardedToDropzone(): void
    {
        $config = $this->extractConfig($this->renderWidget([]));

        $this->assertArrayNotHasKey('resizeWidth', $config['options'], 'Null options must stay out of the payload so Dropzone keeps its own defaults.');
        $this->assertArrayNotHasKey('maxFilesize', $config['options']);
        $this->assertSame(1, $config['options']['maxFiles']);
        $this->assertTrue($config['options']['addRemoveLinks']);
    }

    public function testMaxFilesizeIsForwardedWhenSet(): void
    {
        $config = $this->extractConfig($this->renderWidget([], ['maxFilesize' => 50]));

        $this->assertSame(50, $config['options']['maxFilesize']);
    }

    /**
     * Dropzone.js 6 dispatches its own DOM events under the `dropzone:` prefix,
     * with a different detail shape. Sharing the prefix would deliver two
     * incompatible events under one name to any listener.
     */
    public function testWidgetEventsDoNotCollideWithDropzoneOwnDomEvents(): void
    {
        $script = $this->extractInlineScript($this->renderWidget([]));

        $this->assertStringContainsString("'symfony-dropzone:' + name", $script);
        $this->assertStringNotContainsString("'dropzone:' + name", $script);
    }

    public function testRemoveUrlCarriesAnUnambiguousPlaceholder(): void
    {
        $config = $this->extractConfig($this->renderWidget([]));

        $this->assertStringContainsString('__DROPZONE_FILE_ID__', $config['removeUrl']);
        $this->assertSame('DELETE', $config['removeMethod']);
    }

    /**
     * @param list<FileFixture> $files
     * @param array<string, mixed> $options
     */
    private function renderWidget(array $files, array $options = []): string
    {
        $factory = $this->createFormFactory();

        $form = $factory->createBuilder(FormType::class, ['medias' => $files ?: null])
            ->add('medias', DropzoneType::class, array_merge([
                'class' => FileFixture::class,
                'uploadHandler' => 'app_upload',
                'removeHandler' => 'app_remove',
            ], $options))
            ->getForm();

        return $this->renderer()->searchAndRenderBlock($form->createView()->children['medias'], 'widget');
    }

    private function createFormFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addType(new DropzoneType($this->createMock(EntityManagerInterface::class)))
            ->getFormFactory();
    }

    private function renderer(): FormRenderer
    {
        $bridgeViews = \dirname((new \ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form';

        $twig = new Environment(new FilesystemLoader([
            __DIR__ . '/../../src/Resources/views/Form',
            $bridgeViews,
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension());
        $twig->addFunction(new TwigFunction('path', static function (string $route, array $parameters = []): string {
            $path = '/' . str_replace('_', '/', $route);

            foreach ($parameters as $key => $value) {
                $path .= '/' . rawurlencode((string) $value);
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
