<?php
require_once("csvReader.php");

class redirectTester
{
    private $curl = null;
    private $proxy = '';

    public $find = '';
    public $replace = '';

    public $results = [];

    public $resultsCount = 0;
    public $successCount = 0;
    public $failureCount = 0;
    public $count200 = 0;
    public $count301 = 0;
    public $count404 = 0;

    public function __construct($find, $replace)
    {
        $this->find = $find;
        $this->replace = $replace;
    }

    public function curlSetup($proxy, $user, $password)
    {
        $this->proxy = $proxy;
        $this->curl = setup_curl($proxy, $user, $password);
    }

    public function getSuccesses()
    {
        return $this->getRowsByStatus('Success');
    }

    public function getFailures()
    {
        return $this->getRowsByStatus('Error');
    }

    private function getRowsByStatus($status)
    {
        foreach ($this->results as $result) {
            if ($result['result'] === $status) {
                yield $result;
            }
        }
    }

    public function getOriginals()
    {
        return array_column($this->results, 'original');
    }

    public function getExpecteds()
    {
        return array_column($this->results, 'expected');
    }

    public function processCSVFile($filename)
    {
        $csvReader = new csvReader($filename);
        foreach($csvReader as $rowNumber=>$row) {
            if (count($row) === 2) {
                $this->addRow($this->processRow($rowNumber, $row));
            }
        }
    }

    private function addRow($result)
    {
        if (!$result) {
            return;
        }

        if ($result['result'] === 'Success') { $this->successCount++; }
        if ($result['result'] === 'Error')   { $this->failureCount++; }
        if ($result['http_code'] == '200')   { $this->count200++; }
        if ($result['http_code'] == '301')   { $this->count301++; }
        if ($result['http_code'] == '404')   { $this->count404++; }

        $this->resultsCount++;

        $this->results[] = $result;
    }

    private function processRow($rowNumber, $row)
    {
        $original_url = $this->formatUrlString($row[0]);
        $expected_url = $this->formatUrlString($row[1]);
        $parsed_original = parse_url($original_url);
        $parsed_expected = parse_url($expected_url);

        if (!array_key_exists('scheme', $parsed_original)) {
            return null;
        }

        $visit = visit_url($this->curl, $original_url, $this->proxy);
        $actual = $this->getActualUrl($visit);

        return [
            'row'       => $rowNumber+1,
            'original'  => $original_url,
            'expected'  => $expected_url,
            'actual'    => $actual,
            'http_code' => $visit['http_code'],
            'result'    => $this->checkSuccess($expected_url, $parsed_expected, $actual),
        ];

    }

    private function checkSuccess($expected_url, $parsed_expected, $actual_url)
    {
        // Do we expect a particular URL?
        if (array_key_exists('scheme', $parsed_expected)) {
            if (url_matches($expected_url, $actual_url)) {
                return 'Success';
            }
            return 'Error';
        }
        return '';
    }

    private function getActualUrl($visit)
    {
        if ($visit['http_code'] == HTTP_STATUS_MOVED_PERMANENTLY) {
            return $this->handleRedirects($visit);
        }
        return $visit['url'];
    }

    private function handleRedirects($visit)
    {
        $actual_url = $visit['target'];
        // Deal with multiple redirections.
        while ($visit['http_code'] == HTTP_STATUS_MOVED_PERMANENTLY) {
            if ($visit['target'] === $actual_url) {   //@todo: I'm really not sure this will ever work?
                break;
            }

            $visit = visit_url($curl, $actual_url, $proxy);
            $actual_url = $visit['target'];
        }
        return $actual_url;
    }

    private function formatUrlString($url)
    {
        $url = trim($url);
        if ($this->find && $this->replace) {
            $url = str_replace($find, $replace, $url);
        }
        return $url;
    }
}
