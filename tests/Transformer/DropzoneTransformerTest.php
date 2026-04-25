<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Transformer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Ethsam\SymfonyDropzone\Transformer\DropzoneTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DropzoneTransformerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EntityRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->repository);
    }

    private function createTransformer(int $maxFiles = 1, string $class = 'App\Entity\Attachment'): DropzoneTransformer
    {
        return new DropzoneTransformer($this->entityManager, [
            'maxFiles' => $maxFiles,
            'class' => $class,
        ]);
    }

    #[Test]
    public function transformNullReturnsNull(): void
    {
        $transformer = $this->createTransformer();

        $this->assertNull($transformer->transform(null));
    }

    #[Test]
    public function transformSingleEntityWrapsInArray(): void
    {
        $transformer = $this->createTransformer(maxFiles: 1);
        $entity = new \stdClass();

        $result = $transformer->transform($entity);

        $this->assertSame(['dropzone' => $entity], $result);
    }

    #[Test]
    public function transformMultipleEntitiesWrapsInArray(): void
    {
        $transformer = $this->createTransformer(maxFiles: 5);
        $collection = [new \stdClass(), new \stdClass()];

        $result = $transformer->transform($collection);

        $this->assertSame(['dropzone' => $collection], $result);
    }

    #[Test]
    public function reverseTransformMissingKeyReturnsNull(): void
    {
        $transformer = $this->createTransformer();

        $this->assertNull($transformer->reverseTransform([]));
        $this->assertNull($transformer->reverseTransform(['other' => 'value']));
    }

    #[Test]
    public function reverseTransformSingleCallsFindOneBy(): void
    {
        $transformer = $this->createTransformer(maxFiles: 1, class: 'App\Entity\Image');
        $entity = new \stdClass();

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Image');

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 42])
            ->willReturn($entity);

        $result = $transformer->reverseTransform(['dropzone' => 42]);

        $this->assertSame($entity, $result);
    }

    #[Test]
    public function reverseTransformMultipleCallsFindBy(): void
    {
        $transformer = $this->createTransformer(maxFiles: 10, class: 'App\Entity\Image');
        $entities = [new \stdClass(), new \stdClass()];

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with('App\Entity\Image');

        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['id' => [1, 2, 3]])
            ->willReturn($entities);

        $result = $transformer->reverseTransform(['dropzone' => [1, 2, 3]]);

        $this->assertSame($entities, $result);
    }
}
