<?php

namespace App\Command;

use App\Entity\Attendance;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:demo:seed-attendances', description: 'Crée des comptes et des présences de test.')]
final class SeedDemoAttendanceCommand extends Command
{
    private const DEMO_PASSWORD = 'DemoVelociraptouch2026!';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventRepository $events,
        private readonly AttendanceRepository $attendances,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event-id', null, InputOption::VALUE_REQUIRED, 'Identifiant de l’événement cible.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $event = $input->getOption('event-id')
            ? $this->events->find((int) $input->getOption('event-id'))
            : ($this->events->findUpcoming()[0] ?? null);

        if ($event === null) {
            $output->writeln('<error>Aucun événement cible trouvé.</error>');
            return Command::FAILURE;
        }

        $users = [
            ['Alex', 'Martin', 'demo.alex@velociraptouch.test'],
            ['Camille', 'Bernard', 'demo.camille@velociraptouch.test'],
            ['Jordan', 'Dubois', 'demo.jordan@velociraptouch.test'],
            ['Léa', 'Thomas', 'demo.lea@velociraptouch.test'],
            ['Maxime', 'Robert', 'demo.maxime@velociraptouch.test'],
            ['Sacha', 'Petit', 'demo.sacha@velociraptouch.test'],
        ];
        $userRepository = $this->entityManager->getRepository(User::class);
        $created = 0;
        $presenceAdded = 0;

        foreach ($users as [$firstName, $lastName, $email]) {
            $user = $userRepository->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $user = (new User())
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setEmail($email)
                    ->setPassword($this->passwordHasher->hashPassword(new User(), self::DEMO_PASSWORD));
                $this->entityManager->persist($user);
                ++$created;
            } elseif ($user->getFirstName() === '' || $user->getLastName() === '') {
                $user->setFirstName($firstName)->setLastName($lastName);
            }

            if (!$this->attendances->findOneBy(['event' => $event, 'user' => $user])) {
                $this->entityManager->persist(new Attendance($event, $user));
                ++$presenceAdded;
            }
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('<info>%d compte(s) créé(s), %d présence(s) ajoutée(s).</info>', $created, $presenceAdded));
        $output->writeln('<comment>Mot de passe des comptes de démonstration : '.self::DEMO_PASSWORD.'</comment>');

        return Command::SUCCESS;
    }
}
