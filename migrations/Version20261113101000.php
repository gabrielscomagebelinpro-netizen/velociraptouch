<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du numéro personnalisé sur les précommandes de maillots';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('shop_preorders')->addColumn('jersey_number', Types::INTEGER, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('shop_preorders')->dropColumn('jersey_number');
    }
}
