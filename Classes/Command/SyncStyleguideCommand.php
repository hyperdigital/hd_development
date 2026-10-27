<?php

declare(strict_types=1);

namespace Hyperdigital\HdDevelopment\Command;

use Hyperdigital\HdDevelopment\Service\StyleguideService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'styleguide:sync',
    description: 'Synchronize styleguide pages and content elements from configuration',
)]
class SyncStyleguideCommand extends Command
{
    public function __construct(
        private readonly StyleguideService $styleguideService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Styleguide Sync');
        $io->text('Starting styleguide synchronization...');

        try {
            $this->styleguideService->syncStyleguides();

            // Definitions outlive the schema - a key that is no longer a column is skipped
            // rather than fatal, and named here so it can be found and fixed.
            $warnings = $this->styleguideService->getWarnings();
            if ($warnings !== []) {
                $io->warning('Some values could not be written:');
                $io->listing($warnings);
            }

            $io->success('Styleguide synchronization completed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            // Not getMessage(): a failed resource check says only "Resource consistency check
            // failed" there and keeps the reason in its messages.
            $io->error('Styleguide synchronization failed: ' . StyleguideService::describe($throwable));
            return Command::FAILURE;
        }
    }
}
