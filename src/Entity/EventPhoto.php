<?php

namespace App\Entity;

use App\Repository\EventPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventPhotoRepository::class)]
#[ORM\Table(name: 'event_photos')]
class EventPhoto
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $fileSize;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Event $event, string $filename, string $originalName, string $mimeType, int $fileSize)
    {
        $this->event = $event;
        $this->filename = $filename;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): Event { return $this->event; }
    public function getFilename(): string { return $this->filename; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getFileSize(): int { return $this->fileSize; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
