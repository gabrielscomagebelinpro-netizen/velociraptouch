<?php

namespace App\Entity;

use App\Repository\SponsorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partenaire affiché dans la section « Nos partenaires » de l'accueil.
 */
#[ORM\Entity(repositoryClass: SponsorRepository::class)]
#[ORM\Table(name: 'sponsors')]
class Sponsor
{
    /** Emplacements possibles sur la page, dans l'ordre d'affichage. */
    public const TIERS = [
        'featured' => 'Partenaire principal (grande carte)',
        'club' => 'Club partenaire (carte pleine largeur)',
        'secondary' => 'Partenaire (carte moyenne)',
        'tertiary' => 'Partenaire (petite carte)',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Indique le nom du partenaire.')]
    #[Assert\Length(max: 120)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['featured', 'club', 'secondary', 'tertiary'], message: 'Choisis un emplacement.')]
    private string $tier = 'tertiary';

    /** Petit libellé au-dessus du nom, par exemple « Partenaire convivialité ». */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Indique le type de partenariat (ex. « Partenaire convivialité »).')]
    #[Assert\Length(max: 120)]
    private string $label = 'Partenaire';

    /** Un paragraphe par ligne. */
    #[ORM\Column(length: 2000)]
    #[Assert\NotBlank(message: 'Écris une courte description.')]
    #[Assert\Length(max: 2000)]
    private string $description = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien Instagram doit être une adresse complète (https://…).', protocols: ['https', 'http'], requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le site internet doit être une adresse complète (https://…).', protocols: ['https', 'http'], requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $websiteUrl = null;

    /** Chemin public de l'image : /assets/images/sponsors/… (fournie avec le site) ou /uploads/sponsors/… (envoyée depuis l'admin). */
    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    private string $imageAlt = '';

    /** Image de petite taille : affichée à sa taille réelle pour ne pas être floue. */
    #[ORM\Column]
    private bool $imageLowres = false;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(?string $name): void { $this->name = trim((string) $name); }

    public function getTier(): string { return $this->tier; }
    public function setTier(?string $tier): void { $this->tier = (string) $tier; }
    public function getTierLabel(): string { return self::TIERS[$this->tier] ?? $this->tier; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(?string $label): void { $this->label = trim((string) $label); }

    public function getDescription(): string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = trim((string) $description); }

    /** @return list<string> */
    public function getParagraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R+/', $this->description) ?: []), static fn (string $line): bool => $line !== ''));
    }

    public function getInstagramUrl(): ?string { return $this->instagramUrl; }
    public function setInstagramUrl(?string $url): void { $this->instagramUrl = self::cleanUrl($url); }

    /** « @compte » tiré du lien Instagram. */
    public function getInstagramHandle(): ?string
    {
        if ($this->instagramUrl === null) {
            return null;
        }
        $path = trim((string) parse_url($this->instagramUrl, PHP_URL_PATH), '/');
        $handle = explode('/', $path)[0] ?? '';

        return $handle !== '' ? '@'.$handle : 'Instagram';
    }

    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function setWebsiteUrl(?string $url): void { $this->websiteUrl = self::cleanUrl($url); }

    /** Nom de domaine affiché pour le site internet, sans « www. ». */
    public function getWebsiteLabel(): ?string
    {
        if ($this->websiteUrl === null) {
            return null;
        }
        $host = (string) parse_url($this->websiteUrl, PHP_URL_HOST);

        return preg_replace('/^www\./i', '', $host) ?: $this->websiteUrl;
    }

    public function getImagePath(): string { return $this->imagePath; }
    public function setImagePath(string $path): void { $this->imagePath = $path; }

    /** Texte saisi dans l'admin (peut être vide). */
    public function getImageAlt(): string { return $this->imageAlt; }
    public function setImageAlt(?string $alt): void { $this->imageAlt = trim((string) $alt); }

    /** Texte alternatif affiché : celui saisi, sinon « Logo <nom> ». */
    public function getAltText(): string { return $this->imageAlt !== '' ? $this->imageAlt : 'Logo '.$this->name; }

    public function isImageLowres(): bool { return $this->imageLowres; }
    public function setImageLowres(bool $lowres): void { $this->imageLowres = $lowres; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }

    private static function cleanUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : $url;
    }
}
