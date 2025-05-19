<?php

namespace App\Controller;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SignageController extends AbstractController
{
    #[Route('/', name: 'app_signage')]
    public function index(): Response
    {
        return $this->render('signage/index.html.twig', ['dates' => $this->readDates()]);
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
        $crawler->addHtmlContent($html);
        $allNodes = $crawler->filter('.single-ticket[data-value]');
        $result = [];
        foreach ($allNodes as $node) {
            $parsingResult = $this->parseNode($node);
            if ($parsingResult) {
                $result[] = $parsingResult;
            }
            if (count($result) === 7) {
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
            // @todo Send an email here
            return false;
        }
        if (!$matches['entry']) {
            $matches['entry'] = $matches['start'];
        }
        if (!str_contains($matches['entry'], '.')) {
            $matches['entry'] = $matches['entry'] . '.00';
        }
        return [
            'date' => Datetime::createFromFormat('d.m.Y', $eventDate),
            'title' => $eventText,
            'time' => $matches['entry'],
            'location' => $matches['location'],
            'identifier' => str_replace(' ', '', strtolower($matches['location'])),
        ];
    }
}
