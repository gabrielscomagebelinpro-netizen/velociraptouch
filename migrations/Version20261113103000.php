<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression du statut des précommandes';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('shop_preorders')->dropColumn('status');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_preorders');
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'new']);
    }
}
