<?php

declare(strict_types=1);

namespace Ethsam\SymfonyDropzone\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * A real mapped entity, needed because the single-file mode of the widget builds
 * an EntityType, which loads its choices through Doctrine. FileFixture cannot
 * play that role: it is a plain object with promoted readonly properties.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stored_file')]
class StoredFile implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $filename = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $src = '';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $size = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mimetype = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function setSrc(string $src): self
    {
        $this->src = $src;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function setMimetype(?string $mimetype): self
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * The widget renders the entity itself into the hidden field, so it has to
     * be stringable. This is an undocumented requirement of the bundle.
     */
    public function __toString(): string
    {
        return (string) $this->id;
    }
}
