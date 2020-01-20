<?php

/**
 * This is a lib to crawl the Academic Network Systems.
 * You can achieve easely the querying of grade/schedule/cet/free classroom ...
 *
 * @author Ning Luo <luoning@Luoning.me>
 * @link https://github.com/lndj/Lcrawl
 * @license  MIT
 */

namespace ZfSpider\Traits;

use Symfony\Component\DomCrawler\Crawler;

/**
 * This is trait to parser data from HTML.
 */
trait  Parser
{
    /**
     * Paser the schedule data.
     *
     * @param Object $body
     * @return array
     */
    public function getScheduleTable($body)
    {
        $crawler = new Crawler((string)$body);
        $crawler = $crawler->filter('#Table1');
        $page = $crawler->children();
        //delete line 1、2;
        $page = $page->reduce(function (Crawler $node, $i) {
            if ($i == 0 || $i == 1) {
                return false;
            }
        });
        //to array
        $array = $page->each(function (Crawler $node, $i) {
            return $node->children()->each(function (Crawler $node, $j) {
                $span = (int)$node->attr('rowspan') ?: 0;
                return [$node->html(), $span];
            });
        });

        //If there are some classes in the table is in two or more lines,
        //insert it into the next lines in $array.
        //Thanks for @CheukFung
        $line_count = count($array);
        $schedule = [];
        for ($i = 0; $i < $line_count; $i++) {  //lines
            for ($j = 0; $j < 9; $j++) {    //rows
                if (isset($array[$i][$j])) {
                    $k = $array[$i][$j][1];
                    while (--$k > 0) { // insert element to next line
                        //Set the span 0
                        $array[$i][$j][1] = 0;
                        $array[$i + $k] = array_merge(
                            array_slice($array[$i + $k], 0, $j),
                            [$array[$i][$j]],
                            array_splice($array[$i + $k], $j)
                        );
                    }
                }
                $schedule[$i][$j] = isset($array[$i][$j][0]) ? $array[$i][$j][0] : '';
            }
        }

        return $schedule;
    }

    /**
     * Parser the common table, like cet, chooseClass, etc.
     *
     * @param Object|string $body
     * @param string $selector
     * @return array
     */
    public function getCommonTable($body, $selector = '#DataGrid1')
    {
        $crawler = new Crawler((string)$body);

        $crawler = $crawler->filter($selector);
        $cet = $crawler->children();
        $data = $cet->each(function (Crawler $node, $i) {
            return $node->children()->each(function (Crawler $node, $j) {
                return trim($node->text()," ");
            });
        });
        //Unset the title.
        unset($data[0]);
        return $data;
    }

    /**
     * Parser the hidden value of HTML form.
     *
     * @param Object|string $body
     * @return string|null
     */
    public function getViewState($body)
    {
        $crawler = new Crawler((string)$body);
        return $crawler->filterXPath('//*[@id="form1"]/input')->attr('value');
    }

    /**
     * When get Grade info, the hidden value is not same as login page.
     *
     * @param Object|string $body
     * @return string|null
     */
    public function getGradeViewState($body)
    {
        $crawler = new Crawler((string)$body);
        return $crawler->filterXPath('//*[@id="Form1"]/input')->attr('value');
    }

    /**
     * Parser the hidden value of HTML form.
     *
     * @param Object|string $body
     * @return string|null
     */
    public function getScheduleViewState($body)
    {
        $crawler = new Crawler((string)$body);
        return $crawler->filterXPath('//*[@id="xskb_form"]/input[3]')->attr('value');
    }

    /**
     * Parser the hidden value of HTML form.
     *
     * @param Object|string $body
     * @return string|null
     */
    public function getExamViewState($body)
    {
        $crawler = new Crawler((string)$body);
        return $crawler->filterXPath('//*[@id="form1"]/input[3]')->attr('value');
    }

    /**
     * Get the "action" when dealing with schedule.
     *
     * @param $body
     * @return string|null
     */
    public function getAction($body){
        $crawler = new Crawler((string)$body);
        return $crawler->filterXPath('//*[@id="xskb_form"]')->attr('action');
    }
}