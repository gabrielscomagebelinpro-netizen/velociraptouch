<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_products')]
class Product
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 2000)]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priceCents = 0;

    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(length: 255)]
    private string $imageAlt = '';

    #[ORM\Column]
    private bool $requiresJerseySize = true;

    #[ORM\Column]
    private bool $requiresShortSize = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): self { $this->name = trim($value); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $value): self { $this->description = trim($value); return $this; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $value): self { $this->priceCents = $value; return $this; }
    public function getPriceEuros(): string { return number_format($this->priceCents / 100, 2, ',', ' '); }
    public function getImagePath(): string { return $this->imagePath; }
    public function setImagePath(string $value): self { $this->imagePath = $value; return $this; }
    public function getImageAlt(): string { return $this->imageAlt !== '' ? $this->imageAlt : $this->name; }
    public function setImageAlt(string $value): self { $this->imageAlt = trim($value); return $this; }
    public function isRequiresJerseySize(): bool { return $this->requiresJerseySize; }
    public function setRequiresJerseySize(bool $value): self { $this->requiresJerseySize = $value; return $this; }
    public function isRequiresShortSize(): bool { return $this->requiresShortSize; }
    public function setRequiresShortSize(bool $value): self { $this->requiresShortSize = $value; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): self { $this->active = $value; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): self { $this->position = $value; return $this; }
}
