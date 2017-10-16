<?php
/**
 * @file
 * Script for testing a list of http redirects.
 */

/**
 * Helper function to output print_r() surrounded by <pre> tags.
 *
 * @param string $input
 *   The text to output.
 */
function print_r_clean($input) {
  echo '<pre>';
  print_r($input);
  echo '</pre>';
}

/**
 * Check if a value is greater than 1.
 *
 * @param int $input
 *   The value to check.
 * @return bool
 *   TRUE if the value is greater than 1.
 */
function more_than_1($input) {
  return $input > 1;
}

/**
 * Prepare a curl resource.
 *
 * @param string $proxy
 *   An http proxy to use, if required.
 *
 * @return resource
 *   a cURL handle on success, false on errors.
 */
function setup_curl($proxy = '', $user = '', $password = '') {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curl, CURLOPT_NOBODY, TRUE);
  curl_setopt($curl, CURLOPT_HEADER, TRUE);
  curl_setopt($curl, CURLOPT_TIMEOUT, 10);
  if ($proxy) {
    curl_setopt($curl, CURLOPT_PROXY, $proxy);
  }

  if ($user && $password) {
    curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $password);
  }

  return $curl;
}

/**
 * Check if a URL is invalid, according to FILTER_VALIDATE_URL.
 *
 * @param string $url
 * @return bool
 *   TRUE if the URL should be considered invalid.
 */
function invalid_url($url) {
  return filter_var($url, FILTER_VALIDATE_URL) === FALSE;
}

/**
 * Visit a URL.
 *
 * @param resource $curl
 *   a cURL handle
 *
 * @param string $url
 *   The URL to visit.
 *
 * @return array
 *   Array containing:
 *     url
 *     status code
 */
function visit_url($curl, $url) {
  $result = array();
  if (substr($url, 0, 4) == "http") {
    curl_setopt($curl, CURLOPT_URL, $url);
    $response = @curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status) {
      preg_match('/\b(?:(?:https?|http):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $response, $matches);

      if (!empty($matches[0])) {
        $result['target'] = $matches[0];
      }
    }
    $result['http_code'] = $status;
    $result['url'] = $url;
  }

  return $result;
}

/**
 * Check if two URLs match.
 *
 * @param string $expected_url
 * @param string $actual_url
 * @return bool
 *   TRUE if the URLs should be considered a match.
 */
function url_matches($expected_url, $actual_url) {
  $match = FALSE;

  // Exact match.
  if ($expected_url == $actual_url) {
    $match = TRUE;
  }

  // Has a trailing slash.
  if ($expected_url . '/' == $actual_url) {
    $match = TRUE;
  }

  return $match;
}

/**
 * Convert an array to CSV output.
 *
 * @param array $array
 *   The input array
 *
 * @return null|string
 *   The CSV output, or NULL if the array is empty
 */
function array2csv(array &$array) {
  if (empty($array)) {
    return NULL;
  }
  ob_start();
  $df = fopen("php://output", 'w');
  fputcsv($df, array_keys(reset($array)));
  foreach ($array as $row) {
    fputcsv($df, $row);
  }
  fclose($df);
  return ob_get_clean();
}

/**
 * Output headers for a filename.
 *
 * @param string $filename
 *   The name of the file.
 */
function download_send_headers($filename) {
  // Disable caching.
  $now = gmdate("D, d M Y H:i:s");
  header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
  header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
  header("Last-Modified: {$now} GMT");

  // Force download.
  header("Content-Type: application/force-download");
  header("Content-Type: application/octet-stream");
  header("Content-Type: application/download");

  // Set disposition / encoding on response body.
  header("Content-Disposition: attachment;filename={$filename}");
  header("Content-Transfer-Encoding: binary");
}

function output_csv_results($resData) {
  $results = unserialize($resData);

  if (is_array($results)) {
    download_send_headers("redirect-test-results_" . date("Y-m-d") . ".csv");
    echo array2csv($results);
  }
  else {
    print_r_clean($results);
  }
}

function ifset($array, $element, $default = '')
{
    return isset($array[$element]) ? $array[$element] : $default;
}