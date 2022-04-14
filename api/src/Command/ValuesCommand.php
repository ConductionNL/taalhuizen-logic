<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Service\ParticipationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValuesCommand extends Command
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
            ->setName('app:gateway:values')
            // the short description shown while running "php bin/console list"
            ->setDescription('Rolls out value changes')
            ->setHelp('This command wil loop all values with a dateTimeValue but no stringValue and set the stringValue on these values')
            ->addArgument('page', InputArgument::OPTIONAL);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Value update tool');
        $io->text('Updating values with dateTimeValue but no stringValue');
        $io->section('Input:');
        $io->text("Start page: {$input->getArgument('page')}");

        $errors = $this->participationService->updateGatewayDateTimeValues($io, $input->getArgument('page') ?? 1);

        if($errors == 0){
            $io->success('All values have been updated');
        } elseif($errors < 20) {
            $io->warning("Some values could not be updated. Failure rate is $errors%");
        } else {
            $io->error("A lot of values could not be updated. Failure rate is $errors%");
            return 1;
        }

        return 0;
    }
}
