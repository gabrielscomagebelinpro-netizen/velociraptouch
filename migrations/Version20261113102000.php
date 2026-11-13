<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Une seule précommande par produit et par utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DELETE older FROM shop_preorders older
             INNER JOIN shop_preorders newer
             ON older.product_id = newer.product_id
             AND older.user_id = newer.user_id
             AND older.id < newer.id',
        );
        $schema->getTable('shop_preorders')->addUniqueIndex(
            ['product_id', 'user_id'],
            'uniq_shop_preorder_product_user',
        );
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('shop_preorders')->dropIndex('uniq_shop_preorder_product_user');
    }
}
