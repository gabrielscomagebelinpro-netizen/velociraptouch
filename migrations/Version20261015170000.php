<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first and last names to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD first_name VARCHAR(100) NOT NULL, ADD last_name VARCHAR(100) NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP first_name, DROP last_name');
    }
}
