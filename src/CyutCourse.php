<?php

namespace Pengjie\CyutCrawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Pengjie\Config\DB;

class CyutCourse
{
    private $client;
    private $crawler;
    private $res;
    private $body;
    private $config;
    private $db;
    private $year;
    private $semester;
    private $department;
    private $grade;
    private $classType;

    public function __construct(Array $config)
    {
        $this->client = new Client();
        // Optional: $this->db = new DB();
        $this->config = $config;
    }

    public function findDepartment($value)
    {
        $department = ([
            'TJ9' => '資通系',
            'TJ4' => '資工系',
            'TJ2' => '資管系',
            'TC6' => '財金系',
            'TC7' => '企管系',
            'TC8' => '保險系',
            'TC9' => '會計系',
            'TCA' => '休閒系',
            'TCJ' => '行銷系',
            'TCL' => '銀髮系',
            'TD4' => '營建系',
            'TD5' => '工管系',
            'TD6' => '應化系',
            'TD7' => '環管系',
            'TE2' => '建築系',
            'TE3' => '工設系',
            'TE4' => '視傳系',
            'TE5' => '景都系',
            'TF1' => '傳播系',
            'TF2' => '應英系',
            'TF3' => '幼保系',
            'TF4' => '社工系',
        ]);

        $findResult = array_key_exists($value, $department);
        if (!$findResult) {
            return false;
        }

        return $department[$value];
    }

    public function settingClientRequest()
    {
        $formParams = [
            'verify'       => './config/cacert.pem',
            'form_params'  => $this->config['config']($this->year, $this->semester, $this->department, $this->grade, $this->classType),
        ];

        $this->res = $this->client->request('POST', $this->config['URI'], $formParams);
        $this->body = (string) $this->res->getBody();

        return $this->body;
    }

    public function setCrawler($body)
    {
        $this->crawler = new Crawler($body);

        return $this->crawler;
    }

    public function crawlerResult($body)
    {
        $result = $this->setCrawler($body)
                       ->filter('tr[style="cursor: hand"] td');

        return $result;
    }

    public function chunckResult($result)
    {
        $tmp = array();

        foreach ($result as $domElement) {
            $t = $domElement->nodeValue;
            $length = mb_strlen($t, 'utf-8');
            $pattern = '/([\x{4E00}-\x{9FA5}]{2}(-?\d?)|^\d((,\d|[A-Z])|-([A-Z]|\d))(,(\d|[A-Z]))?|^((\d|[A-Z])-[A-Z])|^\d)[A-Z]([A-Z]?|\d?)-[A-Z]?[0-9]+.\d?$/u';
            $regex = preg_match($pattern, $t, $matches);

            if ($length >= 6) {
                if ($matches) {
                    $regexForClass = '/^([\x{4E00}-\x{9FA5}]{2}(-?\d?)|^\d((,\d|[A-Z])$|-([A-Z]|\d))(,(\d|[A-Z])$)?|^((\d|[A-Z])-[A-Z])|^\d)/u';
                    $time = $matches[1];
                    $where = preg_split($regexForClass, $t);
                    array_push($tmp, array($time, $where[1]));
                } else {
                    array_push($tmp, $t);
                }
            } else {
                array_push($tmp, $t);
            }
        }

        $chunk = array_chunk($tmp, 19);
        $count = 1; $tag = 0;

        for ($i = 0; $i < count($chunk); $i++) {
            for ($j = 10; $j < 17; $j++) {
                if ($chunk[$i][$j] !== '') {
                    array_push($chunk[$i], $chunk[$i][$j], $count);
                }
                unset($chunk[$i][$j]);
                $count++;
            }
            $count = 1; $tag = 0;
        }

        $soryArrayKey = array();

        foreach ($chunk as $value) {
            $tmpArray = array_map(function ($item) {
                return $item;
            }, $value, array_keys($value));
            array_push($soryArrayKey, $tmpArray);
        }

        return $soryArrayKey;
    }

    public function crawlingDepartmentCourses($year, $semester, $department)
    {
        $this->year = $year;
        $this->semester = $semester;
        $this->department = $department;
        $depName = $this->findDepartment($department);
        $tmp = array();
        $depCourses = array();
        $count = 1;

        do {
            for ($j = 0; $j < count($this->config['classType']); $j++) {
                $this->classType = $this->config['classType'][$j];
                $this->grade = $count;
                $this->settingClientRequest();
                array_push($tmp, ([
                    $this->year,
                    $this->semester,
                    $depName,
                    $this->grade,
                    $this->classType,
                    $this->chunckResult($this->crawlerResult($this->body)),
                ]));
            }
            $count++;
        } while($count < 6);

        for ($i = 0; $i < count($tmp); $i++) {
            if (count($tmp[$i][5]) === 0) {
                unset($tmp[$i]);
            } else {
                array_push($depCourses, $tmp[$i]);
            }
        }

        unset($tmp);

        for ($i = 0; $i < count($depCourses); $i++) {
            for ($j = 0; $j < count($depCourses[$i][3]); $j++) {
                if ($depCourses[$i][3][$j][0] === '') {
                    unset($depCourses[$i][3][$j]);
                }
            }
        }

        return $depCourses;
    }

    public function coursesCrawler($data, $config)
    {
        return $this->db->insertCourses($data, $config);
    }
}
