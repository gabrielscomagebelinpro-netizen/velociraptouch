<?php

namespace App\Command;

use App\Repository\AttendanceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:attendances:cleanup', description: 'Supprime les présences des événements terminés.')]
final class CleanupAttendancesCommand extends Command
{
    public function __construct(private readonly AttendanceRepository $attendances)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->attendances->removeForPastEvents(new \DateTimeImmutable('today'));
        $output->writeln(sprintf('<info>%d présence(s) supprimée(s).</info>', $deleted));
        return Command::SUCCESS;
    }
}
