<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Form;

use Doctrine\ORM\EntityManagerInterface;
use Ethsam\SymfonyDropzone\Form\DropzoneType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DropzoneTypeTest extends TestCase
{
    private DropzoneType $type;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->type = new DropzoneType($entityManager);
    }

    #[Test]
    public function configureOptionsRequiresClassUploadAndRemoveHandlers(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $this->expectException(MissingOptionsException::class);
        $resolver->resolve([]);
    }

    #[Test]
    public function configureOptionsDefaults(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => 'App\Entity\Attachment',
            'uploadHandler' => 'app_upload',
            'removeHandler' => 'app_remove',
        ]);

        $this->assertTrue($options['compound']);
        $this->assertSame('App\Entity\Attachment', $options['class']);
        $this->assertTrue($options['required']);
        $this->assertSame('src', $options['choice_src']);
        $this->assertTrue($options['multiple']);
        $this->assertSame(1, $options['maxFiles']);
        $this->assertSame('app_upload', $options['uploadHandler']);
        $this->assertSame('app_remove', $options['removeHandler']);
        $this->assertSame('POST', $options['uploadHandlerMethod']);
        $this->assertSame('DELETE', $options['removeHandlerMethod']);
        $this->assertSame(0, $options['withCredentials']);
        $this->assertSame(120, $options['thumbnailWidth']);
        $this->assertSame(120, $options['thumbnailHeight']);
        $this->assertSame('crop', $options['thumbnailMethod']);
        $this->assertNull($options['resizeWidth']);
        $this->assertNull($options['resizeHeight']);
        $this->assertNull($options['resizeMimeType']);
        $this->assertSame('contain', $options['resizeMethod']);
        $this->assertSame(1024, $options['filesizeBase']);
        $this->assertSame([], $options['headers']);
        $this->assertSame([], $options['formData']);
        $this->assertTrue($options['ignoreHiddenFiles']);
        $this->assertNull($options['acceptedFiles']);
        $this->assertTrue($options['autoProcessQueue']);
        $this->assertTrue($options['autoQueue']);
        $this->assertTrue($options['addRemoveLinks']);
        $this->assertNull($options['previewsContainer']);
        $this->assertNull($options['maxFilesize']);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function badlyTypedOptionProvider(): iterable
    {
        yield 'maxFiles as a string' => ['maxFiles', '5'];
        yield 'maxFilesize as a string' => ['maxFilesize', '50'];
        yield 'acceptedFiles as an array' => ['acceptedFiles', ['image/*']];
        yield 'headers holding an object' => ['headers', ['X-Token' => new \stdClass()]];
        yield 'formData holding an array' => ['formData', ['meta' => ['nested']]];
        yield 'addRemoveLinks as an int' => ['addRemoveLinks', 1];
        yield 'previewsContainer as a bool' => ['previewsContainer', true];
    }

    #[Test]
    #[DataProvider('badlyTypedOptionProvider')]
    public function optionsAreTypeCheckedBeforeReachingThePage(string $option, mixed $value): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $this->expectException(InvalidOptionsException::class);

        $resolver->resolve([
            'class' => 'App\Entity\Attachment',
            'uploadHandler' => 'app_upload',
            'removeHandler' => 'app_remove',
            $option => $value,
        ]);
    }

    #[Test]
    public function maxFilesizeAcceptsIntegersAndFloats(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $base = [
            'class' => 'App\Entity\Attachment',
            'uploadHandler' => 'app_upload',
            'removeHandler' => 'app_remove',
        ];

        $this->assertSame(50, $resolver->resolve($base + ['maxFilesize' => 50])['maxFilesize']);
        $this->assertSame(0.5, $resolver->resolve($base + ['maxFilesize' => 0.5])['maxFilesize']);
    }

    #[Test]
    public function getBlockPrefix(): void
    {
        $this->assertSame('dropzone', $this->type->getBlockPrefix());
    }
}
