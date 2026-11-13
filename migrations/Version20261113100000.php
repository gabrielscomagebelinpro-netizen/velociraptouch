<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Produits de boutique et précommandes'; }

    public function up(Schema $schema): void
    {
        $products = $schema->createTable('shop_products');
        $products->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $products->addColumn('name', Types::STRING, ['length' => 120]);
        $products->addColumn('description', Types::STRING, ['length' => 2000]);
        $products->addColumn('price_cents', Types::INTEGER);
        $products->addColumn('image_path', Types::STRING, ['length' => 255]);
        $products->addColumn('image_alt', Types::STRING, ['length' => 255]);
        $products->addColumn('requires_jersey_size', Types::BOOLEAN);
        $products->addColumn('requires_short_size', Types::BOOLEAN);
        $products->addColumn('active', Types::BOOLEAN);
        $products->addColumn('position', Types::INTEGER);
        $products->setPrimaryKey(['id']);

        $orders = $schema->createTable('shop_preorders');
        $orders->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $orders->addColumn('product_id', Types::INTEGER);
        $orders->addColumn('user_id', Types::INTEGER, ['notnull' => false]);
        $orders->addColumn('name', Types::STRING, ['length' => 120]);
        $orders->addColumn('email', Types::STRING, ['length' => 180]);
        $orders->addColumn('quantity', Types::INTEGER);
        $orders->addColumn('jersey_size', Types::STRING, ['length' => 20, 'notnull' => false]);
        $orders->addColumn('short_size', Types::STRING, ['length' => 20, 'notnull' => false]);
        $orders->addColumn('notes', Types::STRING, ['length' => 1000]);
        $orders->addColumn('status', Types::STRING, ['length' => 20]);
        $orders->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('shop_products', ['product_id'], ['id'], ['onDelete' => 'RESTRICT']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'SET NULL']);

    }

    public function postUp(Schema $schema): void
    {
        $this->connection->insert('shop_products', [
            'name' => 'Pack maillot + short',
            'description' => 'La tenue complète officielle des Vélociraptouch, pensée pour jouer librement et représenter le collectif.',
            'price_cents' => 6500,
            'image_path' => '/assets/images/brand/logo-velociraptouch.png',
            'image_alt' => 'Tenue officielle Vélociraptouch',
            'requires_jersey_size' => 1,
            'requires_short_size' => 1,
            'active' => 1,
            'position' => 1,
        ]);
        $this->connection->insert('shop_products', [
            'name' => 'Maillot seul',
            'description' => 'Le maillot officiel, léger et respirant, pour les matchs, les entraînements et les moments d’équipe.',
            'price_cents' => 4500,
            'image_path' => '/assets/images/brand/logo-velociraptouch.png',
            'image_alt' => 'Maillot officiel Vélociraptouch',
            'requires_jersey_size' => 1,
            'requires_short_size' => 0,
            'active' => 1,
            'position' => 2,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('shop_preorders');
        $schema->dropTable('shop_products');
    }
}
