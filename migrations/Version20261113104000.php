<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113104000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vérification de la suppression du statut des précommandes';
    }

    public function up(Schema $schema): void
    {
        // La colonne a été supprimée par la migration précédente.
    }

    public function down(Schema $schema): void
    {
        // La migration précédente reste responsable de la restauration historique.
    }
}
