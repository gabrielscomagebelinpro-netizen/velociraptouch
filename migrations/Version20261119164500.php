<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harmonise les index de photos après la migration des albums';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photos RENAME INDEX idx_8c2b7b7171f7e88b TO IDX_3AEF978B71F7E88B');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photos RENAME INDEX IDX_3AEF978B71F7E88B TO idx_8c2b7b7171f7e88b');
    }
}
