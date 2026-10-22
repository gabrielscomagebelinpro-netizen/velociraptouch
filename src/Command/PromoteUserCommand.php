<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:promote-admin', description: 'Accorde le rôle administrateur à un utilisateur.')]
final class PromoteUserCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe si le compte doit être créé.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findOneBy(['email' => $email]) ?? (new User())->setEmail($email);
        $password = (string) $input->getOption('password');
        if ($password !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $password));
        } elseif ($user->getPassword() === '') {
            $output->writeln('<error>Utilise --password pour créer un nouvel utilisateur.</error>');
            return Command::INVALID;
        }
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $output->writeln(sprintf('<info>%s est maintenant administrateur.</info>', $email));
        return Command::SUCCESS;
    }
}
