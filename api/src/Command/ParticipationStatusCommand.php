<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Service\ParticipationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParticipationStatusCommand extends Command
{
    private ParticipationService $participationService;

    public function __construct(ParticipationService $participationService, string $name = null)
    {
        $this->participationService = $participationService;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:participations:status')
            // the short description shown while running "php bin/console list"
            ->setDescription('Updates participations status to completed')
            ->setHelp('This command wil get all participations with status ACTIVE and an end date of today or before that and set the status of all these participations to COMPLETED.')
            ->addArgument('page', InputArgument::OPTIONAL);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Participation update tool');
        $io->text('Updating ACTIVE participations with end date set to today or earlier');
        $io->section('Input:');
        $io->text("Start page: {$input->getArgument('page')}");

        $errors = $this->participationService->updateCompletedParticipations($io, $input->getArgument('page') ?? 1);

        if($errors == 0){
            $io->success('All participations have been updated');
        } elseif($errors < 20) {
            $io->warning("Some participations could not be updated. Failure rate is $errors%");
        } else {
            $io->error("A lot of participations could not be updated. Failure rate is $errors%");
            return 1;
        }

        return 0;
    }
}
