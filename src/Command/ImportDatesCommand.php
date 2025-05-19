<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

#[AsCommand(
    name: 'app:import-dates',
    description: 'Add a short description for your command',
)]
class ImportDatesCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        #if ($input->getOption('option1')) {
        #    // ...
        #}
        $this->readDates();

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }

    protected function readDates(): array
    {
        $url = 'https://zakk.de/programm/alle';
        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_URL,$url);
        $html = curl_exec($ch);
        $crawler = new Crawler(useHtml5Parser: true);
        $crawler->addHtmlContent($html, 'UTF-8');
        $allNodes = $crawler->filter('.single-ticket[data-value]');
        $result = [];
        foreach ($allNodes as $node) {
            $parsingResult = $this->parseNode($node);
            if ($parsingResult) {
                $result[] = $parsingResult;
            }
            if (count($result) > 7) {
                break;
            }
        }
        return $result;
    }

    protected function parseNode($node): array|false
    {
        $crawler = new Crawler($node);
        $eventDate = $node->getAttribute('data-value');
        $eventText = $crawler->filter('h2 a')->first()->text();
        $eventTime = $crawler->filter('p.event-time')->first()->text();
        if (str_contains($eventTime, 'nicht im')) {
            return false;
        }
        preg_match(
            '/^(?<start>\d{1,2}(?:\.\d{1,2})?)\s*Uhr\s*(?:Einlass\s*(?<entry>\d{1,2}(?:\.\d{1,2})?)\s*Uhr\s*)?(?<location>Club|Halle|Kneipe|Tanzraum|Raum 4|Studio|Biergarten)?$/u',
            $eventTime,
            $matches
        );
        if (count($matches) === 0) {
            $foo = '';
        }
        if (!$matches['entry']) {
            $matches['entry'] = $matches['start'];
        }
        return [
            'date' => $eventDate,
            'title' => $eventText,
            'time' => $matches['entry'],
            'location' => $matches['location'],
        ];
    }
}
