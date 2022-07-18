<?php

namespace App\Console;

use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\EmptyCollectionException;
use stringEncode\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseIpAustralia
{
    protected ?string $csrfToken = null;
    protected string $url = 'https://search.ipaustralia.gov.au/trademarks/search/doSearch';
    protected Dom $dom;
    protected string $redirectUrl;
    protected $ch;
    protected SymfonyStyle $io;

    /**
     * @throws Exception
     */
    public function __construct($searchText, SymfonyStyle $io)
    {
        $this->dom = new Dom();
        $this->io = $io;

        // try to get csrf token
        $result = $this->doRequest($searchText);
        $this->searchAndSetCsrfToken($result);

        //try to get redirect url
        $this->doRequest($searchText);
        $requestInfo = curl_getinfo($this->ch);

        if (!$requestInfo['redirect_url']) throw new Exception('Parse Error. Maybe your IP address is blocked.');

        $this->redirectUrl = $requestInfo['redirect_url'];
    }

    public function doRequest($searchText): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$this->url?_csrf=$this->csrfToken");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, realpath(".") . '/storage/cookies.log');
        curl_setopt($ch, CURLOPT_COOKIEFILE, realpath(".") . '/storage/cookies.log');
        curl_setopt($ch, CURLOPT_POSTFIELDS, "wv[0]=$searchText");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $result = curl_exec($ch);
        $this->ch = $ch;

        return $result;
    }


    private function searchAndSetCsrfToken($result): void
    {
        $this->dom->loadStr($result);
        $this->csrfToken = $this->dom->find('meta[name="_csrf"]')->getAttribute('content');
    }

    public function doParse(): array
    {
        $this->dom->loadFromUrl($this->redirectUrl);

        try {
            $pages = $this->dom->find('.goto-last-page')->getAttribute('data-gotopage');
        } catch (EmptyCollectionException $e) {
            $pages = 0;
        }

        $products = [];
        $totalPages = $pages ? $pages + 1 : 1;
        $needToParse = $totalPages;
        $this->io->writeln("<info>Total pages: $totalPages</info>");

        if($totalPages > 3) {
            $needToParse = $this->io->ask('How many pages you want to parse?',$totalPages, function ($number) {
                if (!is_numeric($number)) {
                    throw new \RuntimeException('You must type a number.');
                }
                return (int) $number;
            });
        }

        for ($i = 0; $i <= $pages; $i++) {
            $pageNum = $i+1;
            $this->io->writeln("<info>Parsing Page: $pageNum</info>");
            $url = $this->redirectUrl . '&p=' . $i;

            $this->dom->loadFromUrl($url);

            $tmNumber = $this->dom->find('.qa-tm-number');
            $classes = $this->dom->find('.classes');
            $words = $this->dom->find('.words');

            $this->io->progressStart(count($tmNumber));

            foreach ($tmNumber as $key => $item) {

                $number = $item->text;
                $name = trim($words[$key]->text);
                $class = trim($classes[$key]->text);
                $href = $item->getAttribute('href');

                $image = $this->dom->find('#TM' . $item->text . ' .image > img');
                $status = isset($this->dom->find('#TM' . $item->text . ' .status span')[0])
                    ? trim($this->dom->find('#TM' . $item->text . ' .status span')->text) : 'Status not available';

                $status1 = $this->getFirstStatus(':',$status);
                $status2 = $this->getSecondStatus(':',$status);
                try {
                    $logo = $image->getAttribute('src');
                } catch (EmptyCollectionException $e) {
                    $logo = '';
                }

                $products[] = [
                    'number' => $number,
                    'logo' => $logo,
                    'name' => $name,
                    'classes' => $class,
                    'status1' => $status1,
                    'status2' => $status2,
                    'details' => 'https://search.ipaustralia.gov.au' . $href,
                    'page' => $pageNum,
                    'url' => $url
                ];
                $this->io->progressAdvance();
            }
            $this->io->progressFinish();
            if($needToParse == $pageNum) break;
        }


        return [
            'data' => $products,
            'count' => count($products),
        ];
    }

    public function getSecondStatus($search, $subject): string
    {
        return trim(array_reverse(explode($search, $subject, 2))[0]);
    }

    public function getFirstStatus($search, $subject): string
    {
        $result = strstr($subject, (string) $search, true);

        return trim($result === false ? $subject : $result);
    }

}