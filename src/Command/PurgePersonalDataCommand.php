<?php

namespace App\Command;

use App\Entity\EventPhoto;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Service\ContactMessageStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applique les durées de conservation annoncées dans la politique de confidentialité.
 * À lancer chaque jour par une tâche planifiée (cron).
 */
#[AsCommand(name: 'app:rgpd:purge', description: 'Supprime les données personnelles dont la durée de conservation est dépassée.')]
final class PurgePersonalDataCommand extends Command
{
    // Durées annoncées dans templates/legal/privacy.html.twig : les modifier ensemble.
    private const INACTIVE_ACCOUNT = '-3 years';
    private const CONTACT_MESSAGE = '-1 year';
    private const EVENT_PHOTO = '-3 years';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttendanceRepository $attendances,
        private readonly ContactMessageStore $messages,
        #[Autowire('%kernel.project_dir%/var/uploads/events')]
        private readonly string $eventPhotoDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans rien supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable();

        // 1. Présences aux événements passés.
        $attendances = $dryRun ? 0 : $this->attendances->removeForPastEvents(new \DateTimeImmutable('today'));

        // 2. Comptes sans connexion depuis 3 ans (jamais le dernier administrateur).
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $admins = count(array_filter($users, static fn (User $user): bool => in_array('ROLE_ADMIN', $user->getRoles(), true)));
        $accountLimit = $now->modify(self::INACTIVE_ACCOUNT);
        $deletedAccounts = 0;
        foreach ($users as $user) {
            if ($user->getLastLoginAt() === null || $user->getLastLoginAt() >= $accountLimit) {
                continue;
            }
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                if ($admins <= 1) {
                    continue;
                }
                $admins--;
            }
            $output->writeln(sprintf('Compte inactif : %s (dernière connexion le %s)', $user->getEmail(), $user->getLastLoginAt()->format('d/m/Y')));
            if (!$dryRun) {
                $this->entityManager->remove($user);
            }
            $deletedAccounts++;
        }

        // 3. Photos des événements datant de plus de 3 ans (fichier et enregistrement).
        $photoLimit = $now->modify(self::EVENT_PHOTO);
        $photos = $this->entityManager->createQueryBuilder()
            ->select('photo')
            ->from(EventPhoto::class, 'photo')
            ->join('photo.event', 'event')
            ->where('event.date < :limit')
            ->setParameter('limit', $photoLimit)
            ->getQuery()
            ->getResult();
        foreach ($photos as $photo) {
            if (!$dryRun) {
                $path = $this->eventPhotoDirectory.'/'.$photo->getFilename();
                if (is_file($path) && !unlink($path)) {
                    throw new \RuntimeException(sprintf('Impossible de supprimer la photo %s.', $path));
                }
                $this->entityManager->remove($photo);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        // 4. Messages de contact de plus d'un an.
        $deletedMessages = $this->messages->purgeOlderThan($now->modify(self::CONTACT_MESSAGE), $dryRun);

        $output->writeln(sprintf(
            '<info>%s%d présence(s), %d compte(s) inactif(s), %d photo(s), %d message(s) de contact.</info>',
            $dryRun ? '[Simulation] À supprimer : ' : 'Supprimé : ',
            $attendances,
            $deletedAccounts,
            count($photos),
            $deletedMessages,
        ));

        return Command::SUCCESS;
    }
}
