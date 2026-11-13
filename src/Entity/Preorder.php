<?php

namespace App\Entity;

use App\Repository\PreorderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PreorderRepository::class)]
#[ORM\Table(name: 'shop_preorders', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_shop_preorder_product_user', columns: ['product_id', 'user_id']),
])]
class Preorder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank, Assert\Length(max: 120)]
    private string $name = '';
    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Email]
    private string $email = '';
    #[ORM\Column]
    private int $quantity = 1;
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $jerseySize = null;
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 99)]
    private ?int $jerseyNumber = null;
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $shortSize = null;
    #[ORM\Column(length: 1000)]
    private string $notes = '';
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getProduct(): Product { return $this->product; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $value): self { $this->user = $value; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): self { $this->name = trim($value); return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $value): self { $this->email = mb_strtolower(trim($value)); return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $value): self { $this->quantity = $value; return $this; }
    public function getJerseySize(): ?string { return $this->jerseySize; }
    public function setJerseySize(?string $value): self { $this->jerseySize = $value ?: null; return $this; }
    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }
    public function setJerseyNumber(?int $value): self { $this->jerseyNumber = $value; return $this; }
    public function getShortSize(): ?string { return $this->shortSize; }
    public function setShortSize(?string $value): self { $this->shortSize = $value ?: null; return $this; }
    public function getNotes(): string { return $this->notes; }
    public function setNotes(string $value): self { $this->notes = trim($value); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
