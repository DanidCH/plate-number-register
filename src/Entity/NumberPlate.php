<?php

namespace App\Entity;

use App\Repository\NumberPlateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NumberPlateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NumberPlate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-zA-Z]{2}[0-9 ]+$/')]
    private ?string $numberPlate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 2,
        max: 3,
    )]
    private ?string $initials = null;

    #[ORM\Column(length: 255)]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: [
            'image/heic',
            'image/jpe',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/tiff',
            'image/webp',
        ]
    )]
    private ?string $file = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumberPlate(): ?string
    {
        return $this->numberPlate;
    }

    public function setNumberPlate(string $numberPlate): self
    {
        $this->numberPlate = strtoupper(preg_replace('/\s+/', '', $numberPlate));

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $dateTime): self
    {
        $this->createdAt = $dateTime;
        return $this;
    }

    public function getInitials(): ?string
    {
        return $this->initials;
    }

    public function setInitials(string $initials): self
    {
        $this->initials = $initials;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }
}
