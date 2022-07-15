<?php

namespace App\Console\Commands;

use App\Console\ParseIpAustralia;
use stringEncode\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseCommand extends Command
{

    protected static $defaultName = 'parse';

    protected static $defaultDescription = 'Parse ipaustralia.gov.au';

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $parser = new ParseIpAustralia($input->getArgument('text'), $io);
        $output->writeln('<info>Parser initiated...</info>');
        $parseResult = $parser->doParse();
        $output->writeln("<info>Total Parsed: {$parseResult['count']} items</info>");

        $io->writeln(json_encode($parseResult['data'], JSON_PRETTY_PRINT));
        return Command::SUCCESS;
    }


    protected function configure(): void
    {
        $this->addArgument('text', InputArgument::REQUIRED, 'Text to search');
    }
}