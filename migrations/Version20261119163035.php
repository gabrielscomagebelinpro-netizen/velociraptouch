<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119163035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace les photos liées aux événements par des albums indépendants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE photo_albums (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_photos MODIFY event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_photos ADD album_id INT DEFAULT NULL');
        $this->addSql('INSERT INTO photo_albums (title, description, created_at) SELECT DISTINCT e.title, e.description, NOW() FROM event_photos p INNER JOIN events e ON e.id = p.event_id');
        $this->addSql('UPDATE event_photos p INNER JOIN events e ON e.id = p.event_id INNER JOIN photo_albums a ON a.title = e.title AND a.description = e.description SET p.album_id = a.id');
        $this->addSql('ALTER TABLE event_photos MODIFY album_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_8C2B7B7B63B1B45 ON event_photos (album_id)');
        $this->addSql('ALTER TABLE event_photos ADD CONSTRAINT FK_8C2B7B7B63B1B45 FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La conversion des albums vers les événements ne peut pas être reconstruite automatiquement.');
    }
}
