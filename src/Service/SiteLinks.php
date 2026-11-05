<?php

namespace App\Service;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Liens et images du site modifiables depuis l'administration (onglet « Liens et partenaires »).
 * Une valeur absente de la base retombe sur sa valeur par défaut ci-dessous.
 */
final class SiteLinks
{
    /** Liens modifiables : clé => [libellé, aide, valeur par défaut]. */
    public const LINKS = [
        'licence_sheet_url' => [
            'Google Sheets des licences',
            'Tableau où chacun·e renseigne ses informations de licence (section « Nous rejoindre »). Vide : le bouton renvoie vers le formulaire de contact.',
            'https://docs.google.com/spreadsheets/d/1umq0N9RekM1n3TK-Y2hiqwGQlzIX2D5tcfocfC24qwI/edit',
        ],
        'image_rights_url' => [
            'Google Forms du droit à l’image',
            'Formulaire d’autorisation de droit à l’image (section Agenda). Vide : le bouton est masqué.',
            'https://docs.google.com/forms/d/e/1FAIpQLScnQRGjvyv02XVdlD9zs4aPA9aiKCxUdf6hLBGr5fUWBlOnKQ/viewform',
        ],
        'rugby5_rules_url' => [
            'Règles du rugby à 5',
            'Document des règles du jeu (section « Nous rejoindre »). Vide : le lien est masqué.',
            'https://api.www.ffr.fr/wp-content/uploads/2020/08/R%C3%A8gles-du-jeu-Rugby-%C3%A0-5.pdf',
        ],
    ];

    public const IMAGE_RIGHTS_QR = 'image_rights_qr';
    public const DEFAULT_IMAGE_RIGHTS_QR = '/assets/images/editorial/code-qr-velo.jpg';

    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(
        private readonly SiteSettingRepository $settings,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, string> toutes les valeurs, défauts compris */
    public function all(): array
    {
        if ($this->values === null) {
            $values = array_map(static fn (array $definition): string => $definition[2], self::LINKS);
            $values[self::IMAGE_RIGHTS_QR] = self::DEFAULT_IMAGE_RIGHTS_QR;
            try {
                foreach ($this->settings->findAll() as $setting) {
                    if (array_key_exists($setting->getKey(), $values)) {
                        $values[$setting->getKey()] = $setting->getValue();
                    }
                }
            } catch (DbalException) {
                // Base indisponible ou migration pas encore jouée : le site reste affichable avec les valeurs par défaut.
            }
            $this->values = $values;
        }

        return $this->values;
    }

    public function get(string $key): string
    {
        return $this->all()[$key] ?? '';
    }

    /** Enregistre une valeur (le flush est à la charge de l'appelant). */
    public function set(string $key, string $value): void
    {
        if (!array_key_exists($key, self::LINKS) && $key !== self::IMAGE_RIGHTS_QR) {
            throw new \InvalidArgumentException(sprintf('Réglage inconnu : %s', $key));
        }
        $setting = $this->settings->find($key);
        if ($setting instanceof SiteSetting) {
            $setting->setValue($value);
        } else {
            $this->entityManager->persist(new SiteSetting($key, $value));
        }
        $this->values = null;
    }
}
