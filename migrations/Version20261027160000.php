<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last login date to users (RGPD inactive account purge)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_login_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)"');
        // Les comptes existants commencent leur délai d'inactivité à partir d'aujourd'hui.
        $this->addSql('UPDATE users SET last_login_at = NOW()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_login_at');
    }
}
