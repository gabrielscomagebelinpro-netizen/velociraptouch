<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create persisted event attendances';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_attendances (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_7A3D4A9671F7E88B (event_id), INDEX IDX_7A3D4A96A76ED395 (user_id), UNIQUE INDEX unique_event_user (event_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_attendances ADD CONSTRAINT FK_7A3D4A9671F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_attendances ADD CONSTRAINT FK_7A3D4A96A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_attendances');
    }
}
