<?php

/**
 * This is a lib to crawl the Academic Network Systems.
 * You can achieve easely the querying of grade/schedule/cet/free classroom ...
 *
 * @author Leo Lu <i@leoleoasd.me>
 * @link https://github.com/leoleoasd/zf_spider
 * @license  MIT
 */

namespace ZfSpider\Traits;

use ArrayObject;
use DOMDocument;
use Exception;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;
use ZfSpider\SpiderException;

/**
 * This is trait to parser data from HTML.
 */
trait Parser
{
    /**
     * Paser the schedule data.
     *
     * @param Object $body
     * @return array
     */
    public function getScheduleTable($body)
    {
        try {
            $crawler = new Crawler((string)$body);
            $crawler = $crawler->filter('#Table1');
            $page = $crawler->children();
        } catch (Exception $e) {
            return null;
        }
        //delete line 1、2;
        $page = $page->reduce(function (Crawler $node, $i) {
            return !($i == 0 || $i == 1);
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
    public function getCommonTable($body, $selector)
    {
        $crawler = new Crawler((string)$body);

        try {
            $crawler = $crawler->filter($selector);
            $cet = $crawler->children();
            $data = $cet->each(function (Crawler $node, $i) {
                return $node->children()->each(function (Crawler $node, $j) {
                    return trim($node->text());
                });
            });
            //Unset the title.
            unset($data[0]);
        } catch (Exception $e) {
            return null;
        }
        return $data;
    }

    public function cetData($body) {
        $resp = $this->getCommonTable($body, '#DataGrid1');
        if(is_null($resp)) { return null; }
        $ret = [];
        foreach ($resp as $k => $v) {
            $ret[$k-1] = new stdClass();
            $ret[$k-1]->year = $v[0];
            $ret[$k-1]->term = $v[1];
            $ret[$k-1]->name = $v[2];
            $ret[$k-1]->id = $v[3];
            $ret[$k-1]->date = $v[4];
            $ret[$k-1]->total = $v[5];
            $ret[$k-1]->listening = $v[6];
            $ret[$k-1]->reading = $v[7];
            $ret[$k-1]->comprehensive = $v[8];
        }

        return $ret;
    }

    public function courseSelectData($body) {
        $resp = $this->getCommonTable($body, '#DBGrid');
        if(is_null($resp)) { return null; }
        $ret = [];
        foreach ($resp as $k => $v) {
            $ret[$k-1] = new stdClass();
            $ret[$k-1]->courseSelectId = $v[0];
            $ret[$k-1]->id = $v[1];
            $ret[$k-1]->name = $v[2];
            $ret[$k-1]->type = $v[3];
            $ret[$k-1]->selected = $v[4] == '是';
            $ret[$k-1]->instructor = str_replace(' ', '', $v[5]);
            $ret[$k-1]->credit = $v[6];
            $ret[$k-1]->hours = $v[7];
            $ret[$k-1]->time = $v[8];
            $ret[$k-1]->room = $v[9];
            $ret[$k-1]->textbook = $v[10] == '1';
            $ret[$k-1]->year = substr($v[0], 1, 9);
            $ret[$k-1]->term = substr($v[0], 11, 1);
            $ret[$k-1]->score = -1;
            $ret[$k-1]->gpa = -1;
            $ret[$k-1]->minor_maker = null;
            $ret[$k-1]->belong = null;
            $ret[$k-1]->makeup_score = null;
            $ret[$k-1]->retake_score = null;
            $ret[$k-1]->academy = null;
            $ret[$k-1]->comment = null;
            $ret[$k-1]->retake_maker = null;
        }

        return $ret;
    }

    /**
     * Parser the hidden value of HTML form.
     *
     * @param Object|string $body
     * @return string[]|null
     */
    public function getCourseSelectViewState($body)
    {
        try {
            $crawler = new Crawler((string)$body);
            return [
                $crawler->filterXPath('//*[@id="Form1"]/input[3]')->attr('value'),
                $crawler->filterXPath('//select[@name="ddlXN"]/option[@selected="selected"]')->attr('value'),
                $crawler->filterXPath('//select[@name="ddlXQ"]/option[@selected="selected"]')->attr('value'),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    public function detailData(string $body){
        $doc = new DOMDocument();
        $http_response = iconv("gb2312","utf-8//IGNORE", $body);
        @$doc->loadHTML(mb_convert_encoding($http_response, 'HTML-ENTITIES', 'UTF-8'));
        $data = [
            'xh' => 'student_id',
            'lbl_xszh' => 'student_cert_id',
            'lbl_TELLX' => 'tel_type',
            'xm' => 'name',
            'lbl_pyfx' => 'orientation',
            'lbl_TELNUMBER' => 'tel',
            'lbl_zym' => 'prev_name',
            'lbl_zyfx' => 'professional_orientation',
            'lbl_jtyb' => 'addr_code',
            'lbl_xb' => 'sex',
            'lbl_rxrq' => 'enroll_date',
            'lbl_jtdh' => 'home_tel',
            'lbl_csrq' => 'birth_date',
            'lbl_byzx' => 'high_school',
            'lbl_fqxm' => 'father_name',
            'lbl_mz' => 'minority',
            'lbl_ssh' => 'dorm',
            'lbl_fqdw' => 'father_co',
            'lbl_jg' => 'hometown',
            'lbl_dzyxdz' => 'email',
            'lbl_fqdwyb' => 'father_co_addr_code',
            'lbl_zzmm' => 'politics',
            'lbl_lxdh' => 'student_tel',
            'lbl_mqxm' => 'mother_name',
            'lbl_lydq' => 'from_city',
            'lbl_yzbm' => 'student_addr_code',
            'lbl_mqdw' => 'mother_co',
            'lbl_lys' => 'from_province',
            'lbl_zkzh' => 'exam_id',
            'lbl_mqdwyb' => 'mother_co_addr_code',
            'lbl_csd' => 'birth_place',
            'lbl_sfzh' => 'identification_id',
            'lbl_fqdwdh' => 'father_co_tel',
            'lbl_jkzk' => 'health_status',
            'lbl_CC' => 'level',
            'lbl_mqdwdh' => 'mother_co_tel',
            'lbl_xy' => 'college',
            'lbl_gatm' => 'hk_id',
            'lbl_jtdz' => 'home_addr',
            'lbl_xi' => 'faculty',
            'lbl_bdh' => 'admit_no',
            'lbl_jtszd' => 'home_place',
            'lbl_zymc' => 'major',
            'lbl_SFGSPYDY' => 'athlete',
            'lbl_JXBMC' => 'class_name',
            'lbl_yydj' => 'english_level',
            'lbl_xzb' => 'class_id',
            'lbl_YYCJ' => 'english_score',
            'lbl_xz' => 'years',
            'lbl_LJBYM' => 'enroll_page',
            'lbl_xxnx' => 'years_limit',
            'lbl_tc' => 'speciality',
            'lbl_xjzt' => 'status',
            'lbl_RDSJ' => 'politics_enroll_date',
            'lbl_dqszj' => 'current_year',
            'lbl_ccqj' => 'train_dest',
            'lbl_ksh' => 'exam_stu_id',
            'lbl_xxxs' => 'learn_type',
            'lbl_xmpyo' => 'pinyin',
            'lbl_zjlx' => 'identification_type',
            'lbl_sfzz' => 'working',
            'lbl_fqzjlx' => 'mother_type',
            'lbl_fqzjhm' => 'mother_id',
            'lbl_mqzjlx' => 'father_type',
            'lbl_mqzjhm' => 'father_id',
        ];
        $result = new stdClass();
        foreach ($data as $key => $value){
            $result->$value = $doc->getElementById($key)->nodeValue;
        }
        $result->major_change = null;
        $major_change = $this->getCommonTable($body, '#DataGrid1');
        if ($major_change != null) {
            $result->major_change = [];
            foreach ($major_change as $k => $v) {
                $result->major_change[$k-1] = new stdClass();
                $result->major_change[$k-1]->type = $v[0];
                $result->major_change[$k-1]->reason = $v[1];
                $result->major_change[$k-1]->prevStatus = $v[2];
                $result->major_change[$k-1]->prevClass = $v[3];
                $result->major_change[$k-1]->prevMajor = $v[4];
                $result->major_change[$k-1]->prevCollege = $v[5];
                $result->major_change[$k-1]->prevYear = $v[6];
                $result->major_change[$k-1]->status = $v[7];
                $result->major_change[$k-1]->class = $v[8];
                $result->major_change[$k-1]->major = $v[9];
                $result->major_change[$k-1]->college = $v[10];
                $result->major_change[$k-1]->year = $v[11];
            }
        }

        return $result;
    }
}
