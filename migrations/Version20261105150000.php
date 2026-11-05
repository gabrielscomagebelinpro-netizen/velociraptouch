<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Liens et partenaires modifiables depuis l'administration.
 * Les tables sont décrites avec l'API Schema (valable pour MySQL comme pour la base SQLite de preview)
 * et remplies avec le contenu jusque-là écrit en dur dans les gabarits.
 */
final class Version20261105150000 extends AbstractMigration
{
    private const SPONSORS = [
        ['Ethik Sport Club', 'featured', 'Partenaire principal', 'Le sport, la santé et le collectif à Montpellier.', 'https://www.instagram.com/ethiksportclub_montpellier/', null, '/assets/images/sponsors/ethik-sport-club.jpeg', 'Photo du profil Ethik Sport Club Montpellier', false],
        ['Little Matt Panuozzo', 'featured', 'Partenaire principal', 'Panuozzi italiens et gourmandise au cœur de Montpellier.', 'https://www.instagram.com/littlemattpanuozzo/', null, '/assets/images/sponsors/little-matt-panuozzo.jpeg', 'Photo du profil Little Matt Panuozzo', false],
        ['Les Novas', 'club', 'Club partenaire · rugby à XV', "L’équipe féminine de rugby à XV de Montpellier et ses alentours, portée par le MUC Rugby et le Rugby Club Mauguio Carnon, engagée en Fédérale 2 dans le championnat d’Occitanie.\nLes Vélociraptouch et les Novas avancent ensemble : une passerelle naturelle pour celles qui ont envie de passer du toucher au contact.", 'https://www.instagram.com/novasrugbymontpellier/', null, '/assets/images/sponsors/novas-rugby.jpg', 'Logo des Novas Rugby', true],
        ['BEERS! Montpellier', 'secondary', 'Partenaire convivialité', 'Same place, more fun.', 'https://www.instagram.com/beers_montpellier/', null, '/assets/images/sponsors/logo-beers-montpellier.png', 'Logo BEERS! Montpellier', false],
        ['La Fumerie Montpellier', 'secondary', 'Partenaire convivialité', 'Vodka bar · 8 rue de l’Aiguillerie · Montpellier', 'https://www.instagram.com/lafumerie_mtp/?hl=fr', null, '/assets/images/sponsors/logo-la-fumerie-montpellier.jpg', 'Logo La Fumerie Montpellier', true],
        ['Le Tord Boyaux', 'tertiary', 'Partenaire', 'Bar et convivialité à Montpellier.', 'https://www.instagram.com/letordboyauxmontpellier/', null, '/assets/images/sponsors/le-tord-boyaux.jpg', 'Photo du profil Le Tord Boyaux Montpellier', true],
        ['Tuk Tuk Team', 'tertiary', 'Partenaire', "L'équipe Tuk Tuk à Montpellier.", 'https://www.instagram.com/tuktukteamontpellier/', null, '/assets/images/sponsors/tuk-tuk-team.jpg', 'Photo du profil Tuk Tuk Team Montpellier', true],
        ['Sarda Avocat', 'tertiary', 'Partenaire juridique', 'Avocat pénaliste · 6 passage Lonjon · 34000 Montpellier', 'https://www.instagram.com/sarda_avocat/', null, '/assets/images/sponsors/logo-sarda-avocat-montpellier.png', 'Logo Sarda Avocat Montpellier', false],
    ];

    public function getDescription(): string
    {
        return 'Liens du site (Google Sheets, Google Forms, règles) et partenaires gérés depuis l’administration';
    }

    public function up(Schema $schema): void
    {
        $settings = $schema->createTable('site_settings');
        $settings->addColumn('setting_key', Types::STRING, ['length' => 64]);
        $settings->addColumn('setting_value', Types::STRING, ['length' => 1000]);
        $settings->setPrimaryKey(['setting_key']);

        $sponsors = $schema->createTable('sponsors');
        $sponsors->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $sponsors->addColumn('name', Types::STRING, ['length' => 120]);
        $sponsors->addColumn('tier', Types::STRING, ['length' => 20]);
        $sponsors->addColumn('label', Types::STRING, ['length' => 120]);
        $sponsors->addColumn('description', Types::STRING, ['length' => 2000]);
        $sponsors->addColumn('instagram_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $sponsors->addColumn('website_url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $sponsors->addColumn('image_path', Types::STRING, ['length' => 255]);
        $sponsors->addColumn('image_alt', Types::STRING, ['length' => 255]);
        $sponsors->addColumn('image_lowres', Types::BOOLEAN);
        $sponsors->addColumn('position', Types::INTEGER);
        $sponsors->setPrimaryKey(['id']);
    }

    public function postUp(Schema $schema): void
    {
        $positions = [];
        foreach (self::SPONSORS as [$name, $tier, $label, $description, $instagram, $website, $image, $alt, $lowres]) {
            $positions[$tier] = ($positions[$tier] ?? 0) + 1;
            $this->connection->insert('sponsors', [
                'name' => $name,
                'tier' => $tier,
                'label' => $label,
                'description' => $description,
                'instagram_url' => $instagram,
                'website_url' => $website,
                'image_path' => $image,
                'image_alt' => $alt,
                'image_lowres' => $lowres,
                'position' => $positions[$tier],
            ], ['image_lowres' => Types::BOOLEAN]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sponsors');
        $schema->dropTable('site_settings');
    }
}
