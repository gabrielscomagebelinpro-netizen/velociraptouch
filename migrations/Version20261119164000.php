<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harmonise le schéma des albums photo avec les métadonnées Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photos CHANGE created_at created_at DATETIME NOT NULL, CHANGE album_id album_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photos RENAME INDEX idx_8c2b7b7b63b1b45 TO IDX_3AEF978B1137ABCF');
        $this->addSql('ALTER TABLE photo_albums CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_photos CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE album_id album_id INT NOT NULL');
        $this->addSql('ALTER TABLE event_photos RENAME INDEX IDX_3AEF978B1137ABCF TO idx_8c2b7b7b63b1b45');
        $this->addSql('ALTER TABLE photo_albums CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
