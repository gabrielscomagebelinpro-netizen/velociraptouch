<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorisation de supprimer un produit avec ses précommandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_preorders DROP FOREIGN KEY FK_6C0BF0124584665A');
        $this->addSql('ALTER TABLE shop_preorders ADD CONSTRAINT FK_6C0BF0124584665A FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_preorders DROP FOREIGN KEY FK_6C0BF0124584665A');
        $this->addSql('ALTER TABLE shop_preorders ADD CONSTRAINT FK_6C0BF0124584665A FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE RESTRICT');
    }
}
