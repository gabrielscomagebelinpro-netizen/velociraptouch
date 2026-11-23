<?php

namespace App\Command;

use App\Entity\Attendance;
use App\Entity\Event;
use App\Entity\EventPhoto;
use App\Entity\PhotoAlbum;
use App\Entity\Preorder;
use App\Entity\Product;
use App\Entity\User;
use App\Service\ContactMessageStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Remplit une base locale avec un jeu de données de démonstration de tous les types :
 * membres et admin, événements passés et à venir, présences, photos d'événements, albums photo,
 * produits de boutique (formulaire du site et HelloAsso), précommandes et messages de contact.
 *
 * Les identifiants créés sont notés dans var/demo-seed.json : relancer la commande remplace
 * le jeu précédent sans toucher aux données saisies à la main. Interdit en production.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Remplit la base locale avec des données de démonstration de tous les types.')]
final class SeedDemoDataCommand extends Command
{
    private const EMAIL_DOMAIN = 'demo.velociraptouch.test';
    private const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    /** @var array{users: list<int>, events: list<int>, albums: list<int>, products: list<int>, files: list<string>, messages: list<string>} */
    private array $manifest = ['users' => [], 'events' => [], 'albums' => [], 'products' => [], 'files' => [], 'messages' => []];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ContactMessageStore $contactMessages,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Supprime seulement le jeu de démonstration précédent, sans en recréer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment === 'prod') {
            $io->error('Commande réservée aux environnements locaux (APP_ENV=dev ou test).');

            return Command::FAILURE;
        }

        $removed = $this->purgePreviousSeed();
        if ($removed > 0) {
            $io->note(sprintf('Jeu de démonstration précédent supprimé (%d éléments).', $removed));
        }
        if ($input->getOption('purge')) {
            $io->success('Données de démonstration supprimées.');

            return Command::SUCCESS;
        }

        mt_srand(2026);
        $password = 'Demo-Raptor-'.bin2hex(random_bytes(3)).'!7';
        $members = $this->seedUsers($password);
        [$pastEvents, $upcomingEvents] = $this->seedEvents();
        $attendanceCount = $this->seedAttendances($members, $pastEvents, $upcomingEvents);
        $photoCount = $this->seedEventPhotos($pastEvents);
        [$albumCount, $albumPhotoCount] = $this->seedAlbums();
        [$products, $siteProducts] = $this->seedProducts();
        $preorderCount = $this->seedPreorders($members, $siteProducts);
        $messageCount = $this->seedContactMessages();

        $this->entityManager->flush();
        foreach ([...$members, ...$pastEvents, ...$upcomingEvents] as $entity) {
            $this->manifest[$entity instanceof User ? 'users' : 'events'][] = $entity->getId();
        }
        foreach ($products as $product) {
            $this->manifest['products'][] = $product->getId();
        }
        $this->saveManifest();
        $this->writeCredentials($members, $password);

        $io->success('Base locale remplie avec les données de démonstration.');
        $io->table(['Type', 'Nombre'], [
            ['Membres (dont 1 admin « coach »)', count($members)],
            ['Événements passés / à venir', count($pastEvents).' / '.count($upcomingEvents)],
            ['Présences', $attendanceCount],
            ['Photos d’événements', $photoCount],
            ['Albums photo / photos', $albumCount.' / '.$albumPhotoCount],
            ['Produits (site, HelloAsso, masqué)', count($products)],
            ['Précommandes', $preorderCount],
            ['Messages de contact', $messageCount],
        ]);
        $io->writeln('Identifiants des comptes de démonstration : var/preview-demo-accounts.txt');

        return Command::SUCCESS;
    }

    /** @return list<User> */
    private function seedUsers(string $password): array
    {
        $people = [
            ['Camille', 'Coach', true],
            ['Léa', 'Fontaine', false],
            ['Hugo', 'Martinez', false],
            ['Inès', 'Benali', false],
            ['Théo', 'Garnier', false],
            ['Manon', 'Rousseau', false],
            ['Yanis', 'Lefèvre', false],
            ['Chloé', 'Nguyen', false],
            ['Lucas', 'Moreau', false],
            ['Sarah', 'Dupont', false],
            ['Nathan', 'Girard', false],
            ['Jade', 'Lambert', false],
            ['Élodie', 'Faure', false],
            ['Mathis', 'Roux', false],
        ];
        $users = [];
        foreach ($people as [$firstName, $lastName, $isAdmin]) {
            $slug = $this->slug($firstName.'.'.$lastName);
            $user = (new User())
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmail($slug.'@'.self::EMAIL_DOMAIN)
                ->setRoles($isAdmin ? ['ROLE_ADMIN'] : [])
                ->setLastLoginAt(new \DateTimeImmutable(sprintf('-%d days', mt_rand(0, 60))));
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $this->entityManager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    /** @return array{0: list<Event>, 1: list<Event>} */
    private function seedEvents(): array
    {
        $past = [
            ['-75 days', 'Entraînement de reprise', "Premier entraînement de la saison au stade de la Rauze.\nÉchauffement, passes et petits matchs à 5."],
            ['-52 days', 'Tournoi de Sète', "Tournoi amical sur la plage avec 8 équipes.\nDépart groupé en covoiturage à 8 h 30."],
            ['-30 days', 'Soirée d’intégration', 'Apéro et jeux pour accueillir les nouvelles et nouveaux, chez notre partenaire BEERS!.'],
            ['-12 days', 'Entraînement sous la pluie', 'Séance maintenue malgré la météo : travail des appuis et du jeu au pied.'],
        ];
        $upcoming = [
            ['+3 days', 'Entraînement du jeudi', "Rendez-vous à 19 h au stade.\nPense à ta gourde et à tes crampons."],
            ['+10 days', 'Tournoi de Noël à Nîmes', "Tournoi mixte, équipes de 6 joueuses et joueurs.\nInscription avant le 1er décembre."],
            ['+17 days', 'Initiation portes ouvertes', 'Séance découverte ouverte à toutes et tous : viens avec un·e ami·e, aucune expérience demandée.'],
            ['+24 days', 'Assemblée générale', 'Bilan de la saison, élection du bureau et présentation des nouveaux maillots.'],
            ['+45 days', 'Tournoi régional d’Occitanie', 'Compétition sur deux jours à Toulouse. Hébergement organisé par le club.'],
        ];
        $create = function (array $rows): array {
            $events = [];
            foreach ($rows as [$offset, $title, $description]) {
                $event = (new Event())
                    ->setDate(new \DateTimeImmutable('today '.$offset))
                    ->setTitle($title)
                    ->setDescription($description);
                $this->entityManager->persist($event);
                $events[] = $event;
            }

            return $events;
        };

        return [$create($past), $create($upcoming)];
    }

    /** @param list<User> $members @param list<Event> $past @param list<Event> $upcoming */
    private function seedAttendances(array $members, array $past, array $upcoming): int
    {
        $count = 0;
        // Un événement à venir reste sans inscrit, pour voir l'état vide.
        foreach ([...$past, ...array_slice($upcoming, 0, -1)] as $index => $event) {
            $participants = $members;
            shuffle($participants);
            foreach (array_slice($participants, 0, 3 + ($index * 3) % 9) as $member) {
                $this->entityManager->persist(new Attendance($event, $member));
                ++$count;
            }
        }

        return $count;
    }

    /** @param list<Event> $past */
    private function seedEventPhotos(array $past): int
    {
        $count = 0;
        foreach (array_slice($past, 0, 3) as $index => $event) {
            for ($i = 1; $i <= 3 + $index; ++$i) {
                [$filename, $size] = $this->createPhotoFile($event->getTitle(), $i);
                $this->entityManager->persist(new EventPhoto($event, $filename, $this->slug($event->getTitle()).'-'.$i.'.jpg', 'image/jpeg', $size));
                ++$count;
            }
        }

        return $count;
    }

    /** @return array{0: int, 1: int} */
    private function seedAlbums(): array
    {
        $albums = [
            ['Saison 2025-2026', 'Les meilleurs moments de la saison : entraînements, tournois et troisièmes mi-temps.', 5],
            ['Shooting des nouveaux maillots', 'Séance photo avec les maillots de la nouvelle collection.', 4],
            ['Album vide', 'Un album sans photo, pour vérifier l’affichage de l’état vide.', 0],
        ];
        $photoCount = 0;
        foreach ($albums as [$title, $description, $photos]) {
            $album = (new PhotoAlbum())->setTitle($title)->setDescription($description);
            $this->entityManager->persist($album);
            for ($i = 1; $i <= $photos; ++$i) {
                [$filename, $size] = $this->createPhotoFile($title, $i);
                $this->entityManager->persist(new EventPhoto($album, $filename, $this->slug($title).'-'.$i.'.jpg', 'image/jpeg', $size));
                ++$photoCount;
            }
            $this->entityManager->flush();
            $this->manifest['albums'][] = $album->getId();
        }

        return [count($albums), $photoCount];
    }

    /** @return array{0: list<Product>, 1: list<Product>} */
    private function seedProducts(): array
    {
        $rows = [
            ['Maillot officiel 2026', "Maillot de match turquoise et bleu nuit, floqué à ton numéro.\nTissu respirant et coupe ajustée.", 3500, true, false, true, null],
            ['Short de match', 'Short bleu nuit avec le logo du club, poches zippées.', 2200, false, true, true, null],
            ['Pack maillot + short', 'La tenue complète à prix réduit pour les tournois.', 5200, true, true, true, null],
            ['Sweat à capuche', "Sweat chaud et doux pour les soirées d'après-match.", 4000, false, false, true, 'https://www.helloasso.com/associations/velociraptouch/boutiques/sweat-a-capuche'],
            ['Gourde isotherme', 'Gourde inox 500 ml gravée Vélociraptouch.', 1500, false, false, true, 'https://www.helloasso.com/associations/velociraptouch/boutiques/gourde'],
            ['Casquette (bientôt)', 'Produit masqué : préparé dans l’admin mais pas encore en vente.', 1800, false, false, false, null],
        ];
        $products = [];
        $siteProducts = [];
        foreach ($rows as $position => [$name, $description, $price, $jersey, $short, $active, $helloasso]) {
            $product = (new Product())
                ->setName($name)
                ->setDescription($description)
                ->setPriceCents($price)
                ->setImagePath($this->createProductImage($name, $position))
                ->setImageAlt('Visuel de démonstration : '.$name)
                ->setRequiresJerseySize($jersey)
                ->setRequiresShortSize($short)
                ->setActive($active)
                ->setPosition($position + 1)
                ->setHelloassoUrl($helloasso);
            $this->entityManager->persist($product);
            $products[] = $product;
            if ($active && $helloasso === null) {
                $siteProducts[] = $product;
            }
        }

        return [$products, $siteProducts];
    }

    /** @param list<User> $members @param list<Product> $siteProducts */
    private function seedPreorders(array $members, array $siteProducts): int
    {
        $notes = ['', '', 'Flocage « RAPTOR » au dos si possible.', 'Je peux récupérer la commande à l’entraînement du jeudi.', ''];
        $count = 0;
        foreach (array_slice($members, 1, 9) as $index => $member) {
            foreach ($siteProducts as $productIndex => $product) {
                if (($index + $productIndex) % 3 === 2) {
                    continue;
                }
                $preorder = (new Preorder($product))
                    ->setUser($member)
                    ->setName($member->getFirstName().' '.$member->getLastName())
                    ->setEmail($member->getEmail())
                    ->setQuantity(1 + ($index % 3 === 0 ? 1 : 0))
                    ->setJerseySize($product->isRequiresJerseySize() ? self::SIZES[($index + 1) % 6] : null)
                    ->setJerseyNumber($product->isRequiresJerseySize() ? ($index * 7 + 3) % 100 : null)
                    ->setShortSize($product->isRequiresShortSize() ? self::SIZES[($index + 2) % 6] : null)
                    ->setNotes($notes[$index % count($notes)]);
                $this->entityManager->persist($preorder);
                ++$count;
            }
        }

        return $count;
    }

    private function seedContactMessages(): int
    {
        $messages = [
            ['Julie Morel', 'julie.morel@'.self::EMAIL_DOMAIN, "Bonjour, je n'ai jamais joué au rugby touch : est-ce que je peux venir essayer à un entraînement ?"],
            ['Association Sport Pour Tous', 'contact@'.self::EMAIL_DOMAIN, 'Nous organisons un tournoi caritatif en mars et serions ravis d’y accueillir une équipe des Vélociraptouch.'],
            ['Karim Haddad', 'karim.haddad@'.self::EMAIL_DOMAIN, 'Est-ce que les maillots taillent grand ? J’hésite entre M et L.'],
        ];
        foreach ($messages as [$name, $email, $message]) {
            $this->contactMessages->save($name, $email, $message);
            $this->manifest['messages'][] = $email;
        }

        return count($messages);
    }

    /** Crée une « photo » de démonstration (dégradé aux couleurs du club + légende) dans var/uploads/events. */
    private function createPhotoFile(string $caption, int $index): array
    {
        $palette = [[0, 124, 129], [22, 53, 69], [9, 91, 103], [1, 175, 181], [38, 109, 130]];
        [$r1, $g1, $b1] = $palette[($index + strlen($caption)) % 5];
        [$r2, $g2, $b2] = $palette[($index * 2 + 1) % 5];
        $width = 1200;
        $height = 800;
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; ++$y) {
            $t = $y / $height;
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
        $light = imagecolorallocatealpha($image, 255, 251, 251, 90);
        for ($i = 0; $i < 6; ++$i) {
            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(120, 420), mt_rand(120, 420), $light);
        }
        $this->drawText($image, $this->ascii($caption), 60, $height - 200, 5, [255, 251, 251]);
        $this->drawText($image, 'PHOTO '.$index.' - DEMO', 60, $height - 110, 3, [255, 251, 251]);

        $directory = $this->projectDir.'/var/uploads/events';
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $filename = bin2hex(random_bytes(16)).'.jpg';
        imagejpeg($image, $directory.'/'.$filename, 82);
        imagedestroy($image);
        $this->manifest['files'][] = 'var/uploads/events/'.$filename;

        return [$filename, (int) filesize($directory.'/'.$filename)];
    }

    /** Crée un visuel produit carré (fond bleu nuit, bandeau turquoise, nom du produit) dans public/uploads/products. */
    private function createProductImage(string $name, int $index): string
    {
        $size = 800;
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 22, 53, 69));
        $accent = [[0, 124, 129], [1, 175, 181], [9, 91, 103]][$index % 3];
        imagefilledpolygon($image, [0, 470, $size, 380, $size, 560, 0, 650], imagecolorallocate($image, ...$accent));
        $logo = @imagecreatefrompng($this->projectDir.'/public/assets/images/brand/logo-velo-svg.png');
        if ($logo !== false) {
            imagecopyresampled($image, $logo, 270, 70, 0, 0, 260, 260, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
        $this->drawText($image, mb_strtoupper($this->ascii($name)), 50, 690, 4, [255, 251, 251]);

        $directory = $this->projectDir.'/public/uploads/products';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = 'demo-'.bin2hex(random_bytes(8)).'.jpg';
        imagejpeg($image, $directory.'/'.$filename, 85);
        imagedestroy($image);
        $this->manifest['files'][] = 'public/uploads/products/'.$filename;

        return '/uploads/products/'.$filename;
    }

    /** Texte en police intégrée GD, agrandi d'un facteur $scale pour rester lisible. */
    private function drawText(\GdImage $image, string $text, int $x, int $y, int $scale, array $rgb): void
    {
        $font = 5;
        $w = imagefontwidth($font) * strlen($text);
        $h = imagefontheight($font);
        $layer = imagecreatetruecolor($w, $h);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagestring($layer, $font, 0, 0, $text, imagecolorallocate($layer, ...$rgb));
        $maxWidth = imagesx($image) - 2 * $x;
        $scale = max(1, min($scale, intdiv($maxWidth, max(1, $w))));
        imagealphablending($image, true);
        imagecopyresampled($image, $layer, $x, $y, 0, 0, $w * $scale, $h * $scale, $w, $h);
        imagedestroy($layer);
    }

    private function purgePreviousSeed(): int
    {
        $file = $this->manifestPath();
        if (!is_file($file)) {
            return 0;
        }
        $previous = json_decode((string) file_get_contents($file), true) ?: [];
        $removed = 0;
        $connection = $this->entityManager->getConnection();
        $ids = static fn (string $key): array => array_map('intval', $previous[$key] ?? []);

        // Ordre : d'abord ce qui dépend des autres (précommandes, présences, photos), puis le reste.
        if ($ids('products') !== []) {
            $removed += $connection->executeStatement('DELETE FROM shop_preorders WHERE product_id IN (?)', [$ids('products')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        if ($ids('users') !== []) {
            $removed += $connection->executeStatement('DELETE FROM shop_preorders WHERE user_id IN (?)', [$ids('users')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
            $removed += $connection->executeStatement('DELETE FROM event_attendances WHERE user_id IN (?)', [$ids('users')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        if ($ids('events') !== []) {
            $removed += $connection->executeStatement('DELETE FROM event_attendances WHERE event_id IN (?)', [$ids('events')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
            $removed += $connection->executeStatement('DELETE FROM event_photos WHERE event_id IN (?)', [$ids('events')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        if ($ids('albums') !== []) {
            $removed += $connection->executeStatement('DELETE FROM event_photos WHERE album_id IN (?)', [$ids('albums')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
            $removed += $connection->executeStatement('DELETE FROM photo_albums WHERE id IN (?)', [$ids('albums')], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        }
        foreach (['events' => 'events', 'products' => 'shop_products', 'users' => 'users'] as $key => $table) {
            if ($ids($key) !== []) {
                $removed += $connection->executeStatement("DELETE FROM $table WHERE id IN (?)", [$ids($key)], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
            }
        }
        foreach ($previous['files'] ?? [] as $relative) {
            $path = $this->projectDir.'/'.ltrim((string) $relative, '/');
            if (str_contains((string) $relative, '..') === false && is_file($path)) {
                unlink($path);
            }
        }
        $removed += $this->removeContactMessages($previous['messages'] ?? []);
        unlink($file);

        return $removed;
    }

    /** @param list<string> $emails */
    private function removeContactMessages(array $emails): int
    {
        $file = $this->projectDir.'/var/data/messages.json';
        if ($emails === [] || !is_file($file)) {
            return 0;
        }
        $messages = json_decode((string) file_get_contents($file), true) ?: [];
        $kept = array_values(array_filter($messages, static fn (array $m): bool => !in_array($m['email'] ?? '', $emails, true)));
        file_put_contents($file, json_encode($kept, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return count($messages) - count($kept);
    }

    /** @param list<User> $members */
    private function writeCredentials(array $members, string $password): void
    {
        $lines = [
            'Comptes de démonstration de la base locale (créés par « php bin/console app:demo:seed »).',
            'Connexion : prénom + nom + mot de passe. Même mot de passe pour tous les comptes.',
            'mot de passe: '.$password,
            '',
        ];
        foreach ($members as $member) {
            $lines[] = sprintf('%-8s %s %s <%s>', in_array('ROLE_ADMIN', $member->getRoles(), true) ? '[admin]' : '[membre]', $member->getFirstName(), $member->getLastName(), $member->getEmail());
        }
        file_put_contents($this->projectDir.'/var/preview-demo-accounts.txt', implode("\n", $lines)."\n");
    }

    private function saveManifest(): void
    {
        file_put_contents($this->manifestPath(), json_encode($this->manifest, JSON_PRETTY_PRINT));
    }

    private function manifestPath(): string
    {
        return $this->projectDir.'/var/demo-seed.json';
    }

    private function slug(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($this->ascii($text))), '-');
    }

    private function ascii(string $text): string
    {
        // Accents français retirés à la main : iconv//TRANSLIT donne « 'e » pour « é » sous Windows.
        $text = strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
            'À' => 'A', 'Â' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U',
            'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', '’' => "'", '«' => '"', '»' => '"', '·' => '.',
        ]);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $converted === false ? $text : $converted) ?? $text;
    }
}
