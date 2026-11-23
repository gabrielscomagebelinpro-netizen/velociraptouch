<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Boutique : lien HelloAsso de précommande, renseigné par les admins pour chaque produit.
 * Décrit avec l'API Schema (valable pour MySQL comme pour la base SQLite de preview).
 */
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le lien HelloAsso de précommande aux produits de la boutique';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('shop_products')->addColumn('helloasso_url', Types::STRING, ['length' => 500, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('shop_products')->dropColumn('helloasso_url');
    }
}
