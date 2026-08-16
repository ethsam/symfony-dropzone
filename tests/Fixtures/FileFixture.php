<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Fixtures;

/**
 * Minimal stand-in for an application file entity, as consumed by the widget.
 */
final class FileFixture
{
    public function __construct(
        private readonly string|int $id,
        private readonly string $filename,
        private readonly string $src,
        private readonly ?int $size = null,
        private readonly ?string $mimetype = null,
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    /**
     * The hidden inputs backing the collection render the entity itself,
     * so an application entity has to be stringable to work with this bundle.
     */
    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }
}
