<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015170500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Complete names for existing users';
    }

    public function up(Schema $schema): void
    {
        $users = [
            ['Administrateur', 'Principal', 'admin@example.com'],
            ['Gabriel', 'Garcia', 'gabsg66@gmail.com'],
            ['Alex', 'Martin', 'demo.alex@velociraptouch.test'],
            ['Camille', 'Bernard', 'demo.camille@velociraptouch.test'],
            ['Jordan', 'Dubois', 'demo.jordan@velociraptouch.test'],
            ['Léa', 'Thomas', 'demo.lea@velociraptouch.test'],
            ['Maxime', 'Robert', 'demo.maxime@velociraptouch.test'],
            ['Sacha', 'Petit', 'demo.sacha@velociraptouch.test'],
        ];

        foreach ($users as [$firstName, $lastName, $email]) {
            $this->addSql(
                'UPDATE users SET first_name = ?, last_name = ? WHERE email = ?',
                [$firstName, $lastName, $email],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE users SET first_name = '', last_name = ''");
    }
}
