<?php

// src/Command/ConfigureClustersCommand.php

namespace App\Command;

use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScopesCommand extends Command
{
    private UserService $userService;

    public function __construct(UserService $userService, string $name = null)
    {
        $this->userService = $userService;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:usergroups:scopes')
            // the short description shown while running "php bin/console list"
            ->setDescription('Rolls out scope changes over all ')
            ->setHelp('This command wil loop all user groups with set code and add or remove (depending on your directive) scopes on them')
            ->addArgument('directive', InputArgument::REQUIRED)
            ->addArgument('code', InputArgument::REQUIRED)
            ->addOption('scope', 's',InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scope rollout tool');
        $io->text('Rolling out scopes over user groups');
        $io->section('Input:');
        $io->text("User group code: {$input->getArgument('code')}");
        $io->text("Directive: {$input->getArgument('directive')}");
        $io->block(array_merge(['Scopes:'], $input->getOption('scope')));

        $errors = $this->userService->mutateScopes($input->getArgument('directive'), $input->getArgument('code'), $input->getOption('scope'), $io);

        if($errors == 0){
            $io->success('All usergroups have been updated');
        } elseif($errors < 20) {
            $io->warning("Some usergroups could not be updated. Failure rate is $errors%");
        } else {
            $io->error("A lot of user groups could not be updated. Failure rate is $errors%");
            return 1;
        }

        return 0;
    }
}
