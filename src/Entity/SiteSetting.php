<?php

namespace App\Entity;

use App\Repository\SiteSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage du site modifiable depuis l'administration (liens Google Sheets / Forms, QR code…).
 * La liste des réglages possibles et leurs valeurs par défaut est dans App\Service\SiteLinks.
 */
#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
#[ORM\Table(name: 'site_settings')]
class SiteSetting
{
    #[ORM\Id, ORM\Column(name: 'setting_key', length: 64)]
    private string $key;

    #[ORM\Column(name: 'setting_value', length: 1000)]
    private string $value;

    public function __construct(string $key, string $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string { return $this->key; }
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): void { $this->value = $value; }
}
