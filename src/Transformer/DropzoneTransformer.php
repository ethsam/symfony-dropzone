<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Transformer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;

class DropzoneTransformer implements DataTransformerInterface
{
    private EntityManagerInterface $entityManager;
    private array $options;

    public function __construct(EntityManagerInterface $entityManager, array $options)
    {
        $this->entityManager = $entityManager;
        $this->options = $options;
    }

    public function transform(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        return ['dropzone' => $value];
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (!isset($value['dropzone'])) {
            return null;
        }

        if ($this->options['maxFiles'] === 1) {
            return $this->entityManager->getRepository($this->options['class'])->findOneBy(['id' => $value['dropzone']]);
        }

        return $this->entityManager->getRepository($this->options['class'])->findBy(['id' => $value['dropzone']]);
    }
}
