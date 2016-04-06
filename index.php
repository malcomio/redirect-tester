<?php
/**
 * @file
 * Script for testing a list of http redirects.
 */

set_time_limit(0);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

define('HTTP_STATUS_OK', 200);
define('HTTP_STATUS_MOVED_PERMANENTLY', 301);
define('HTTP_STATUS_FOUND', 302);
define('HTTP_STATUS_NOT_FOUND', 404);

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
function setup_curl($proxy = '') {
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curl, CURLOPT_NOBODY, TRUE);
  curl_setopt($curl, CURLOPT_HEADER, TRUE);
  curl_setopt($curl, CURLOPT_TIMEOUT, 10);
  if ($proxy) {
    curl_setopt($curl, CURLOPT_PROXY, $proxy);
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

/**
 * Output the results as a CSV.
 */
if (!empty($_POST['csv_output'])) {

  $results = unserialize($_POST['results']);

  if (is_array($results)) {
    download_send_headers("redirect-test-results_" . date("Y-m-d") . ".csv");
    echo array2csv($results);
    die();
  }
  else {
    print_r_clean($results);
  }
}

$form = '<form method="post" enctype="multipart/form-data">
<div class="panel panel-default">
  <div class="panel-body">
  <div class="form-group">
          <label for="proxy">Proxy</label>
          <input type="text" name="proxy" value=""/>
</div>
<div class="form-group">
          <label for="csv_input">Upload a CSV file</label>
          <input type="file" name="csv_input" class="input-medium" required/>
          <p>See <a href="example.csv">the example CSV file</a> for the expected format</p>
          </div>

        <input type="submit" class="btn"/>
</div>
</div>
      </form>';

$back_to_top_link = '<a href="#">Back to top</a>';

?>
<html>
<head>
  <title>URL Redirection Tester</title>
  <link rel="stylesheet"
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
  <link rel="stylesheet"
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">
  <style>
    tbody {
      word-break: break-word;
    }
  </style>
</head>
<body>
<a href="https://github.com/malcomio/redirect-tester">
  <img style="position: absolute; top: 0; right: 0; border: 0;"
       src="https://camo.githubusercontent.com/e7bbb0521b397edbd5fe43e7f760759336b5e05f/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f677265656e5f3030373230302e706e67"
       alt="Fork me on GitHub"
       data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_green_007200.png">
</a>

<div class="container">

<h1>Redirection checker</h1>
<p>Upload a CSV file containing original and expected redirected URLs to test whether your redirect rules are working as intended.</p>
<p>See <a href="https://github.com/malcomio/redirect-tester/blob/master/README.md">the README</a> for more information.</p>
<?php
if (!array_key_exists('csv_input', $_FILES)) {
  print $form;
}
else {

  $file = fopen($_FILES['csv_input']['tmp_name'], 'r');

  $results = $successes = $failures = array();

  $count_200 = $count_301 = $count_404 = 0;

  $proxy = $_POST['proxy'];
  $curl = setup_curl($proxy);

  $row_number = 0;
  while ($row = fgetcsv($file)) {
    $row_number++;
    if (!empty($row[0]) && !empty($row[1])) {
      $original_url = trim($row[0]);
      $expected_url = trim($row[1]);

      $parsed_original = parse_url($original_url);
      $parsed_expected = parse_url($expected_url);

      // Is there a URL to check?
      if (array_key_exists('scheme', $parsed_original)) {
        $visit = visit_url($curl, $original_url, $proxy);

        $actual_url = '';

        $result = array(
          'row' => $row_number,
          'original' => $original_url,
          'http_code' => $visit['http_code'],
        );

        switch ($visit['http_code']) {
          case HTTP_STATUS_OK:
            $count_200++;
            $actual_url = $visit['url'];
            break;

          case HTTP_STATUS_MOVED_PERMANENTLY:
            $count_301++;
            $actual_url = $visit['target'];
            break;

          case HTTP_STATUS_NOT_FOUND:
            $count_404++;
            $actual_url = $visit['url'];
            break;
        }

        // Deal with multiple redirections.
        while ($visit['http_code'] == HTTP_STATUS_MOVED_PERMANENTLY) {
          if ($visit['target'] == $actual_url) {
            break;
          }

          $visit = visit_url($curl, $actual_url, $proxy);
          $actual_url = $visit['target'];
        }

        $result['actual'] = $actual_url;

        // Do we expect a particular URL?
        if (array_key_exists('scheme', $parsed_expected)) {
          $result['expected'] = $expected_url;

          if ($expected_url == $actual_url) {
            $result['result'] = 'Success';
            $successes[] = $result;
          }
          else {
            $result['result'] = 'Error';
            $failures[] = $result;
          }
        }
        $results[] = $result;
      }
    }
  }

  curl_close($curl);
  $result_count = count($results);
  $success_count = count($successes);
  $failure_count = count($failures);

  $originals = array_column($results, 'original');
  $expecteds = array_column($results, 'expected');
  
  $duplicate_originals = array_filter(array_count_values($originals), 'more_than_1');
  
  $invalid_originals = array_filter(array_column($results, 'original'), 'invalid_url');
  $invalid_expecteds = array_filter(array_column($results, 'expected'), 'invalid_url');
  ?>

  <?php if ($result_count) : ?>
    <div class="panel panel-default" id="summary">
      <div class="panel-heading">
        <h3 class="panel-title">Summary</h3>
      </div>
      <table class="table table-striped table-bordered">
        <thead>
        <tr>
          <th>URLs checked</th>
          <th><?php print HTTP_STATUS_OK; ?></th>
          <th><?php print HTTP_STATUS_MOVED_PERMANENTLY; ?></th>
          <th><?php print HTTP_STATUS_NOT_FOUND; ?></th>
          <?php if ($success_count) : ?>
            <th>Successes</th>
          <?php endif; ?>
          <?php if ($failure_count): ?>
            <th>Errors</th>
          <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td><?php print $result_count; ?></td>
          <td><?php print $count_200; ?></td>
          <td><?php print $count_301; ?></td>
          <td><?php print $count_404; ?></td>
          <?php if ($success_count) : ?>
            <td>
              <a href="#success">
                <?php print $success_count; ?>
              </a>
            </td>
          <?php endif; ?>
          <?php if ($failure_count): ?>
            <td>
              <a href="#failure">
                <?php print $failure_count; ?>
              </a>
            </td>
          <?php endif; ?>
        </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p>
    <a href="index.php" class="btn btn-success">Start again</a>
  </p>

  <form method="post">
    <input type="hidden" name="csv_output" value="true"/>
    <input type="hidden" name="results"
           value="<?php print htmlentities(serialize($results)); ?>"/>
    <input type="submit" class="btn" value="Output as CSV"/>
  </form>
  
  <?php

  print_r_clean($results);
  print_r_clean($originals);

  ?>

  <ul>
    <?php if (!empty($duplicate_originals)): ?>
      <li>
        <a href="#error-duplicates">Duplicate originals</a>
      </li>
    <?php endif; ?>
    <?php if (!empty($invalid_originals)): ?>
      <li>
        <a href="#error-invalid-original">Invalid original URLs</a>
      </li>
    <?php endif; ?>
    <?php if (!empty($invalid_expecteds)): ?>
      <li>
        <a href="#error-invalid-expected">Invalid expected URLs</a>
      </li>
    <?php endif; ?>
    <?php if (!empty($failures)): ?>
      <li>
        <a href="#errors">Errors</a>
      </li>
    <?php endif; ?>
    <?php if (!empty($successes)): ?>
      <li>
        <a href="#successes">Successes</a>
      </li>
    <?php endif; ?>    
  </ul>

  <?php if (!empty($duplicate_originals)): ?>
    <div class="alert alert-danger" role="alert" id="error-duplicates">
      <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
      <span class="sr-only">Error:</span>
      The input contains the following duplicate original URLs:
      <ul>
        <?php foreach ($duplicate_originals as $original => $count): ?>
          <li>
            <?php print $original .' : ' . $count . ' instances'; ?>
            <ul>
              <?php 
              $instances = array_keys($originals, $original);
              foreach ($instances as $instance) {
                print '<li>Row ' . $results[$instance]['row'] . '</li>';               
              }
              ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $back_to_top_link; ?>
  <?php endif; ?>

  <?php if (!empty($invalid_originals)): ?>
    <div class="alert alert-danger" role="alert" id="error-invalid-original">
      <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
      <span class="sr-only">Error:</span>
      The input contains the following invalid original URLs:
      <ul>
        <?php foreach ($invalid_originals as $url): ?>
          <?php
          $instances = array_keys($invalid_originals, $url);
          foreach ($instances as $instance): ?>
            <li>
              Row <?php print $results[$instance]['row']; ?>: <?php print $url; ?>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $back_to_top_link; ?>
  <?php endif; ?>

  <?php if (!empty($invalid_expecteds)): ?>
    <div class="alert alert-danger" role="alert" id="error-invalid-expected">
      <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
      <span class="sr-only">Error:</span>
      The input contains the following invalid expected URLs:
      <ul>
        <?php foreach ($invalid_expecteds as $url): ?>
          <?php
          $instances = array_keys($invalid_expecteds, $url);
          foreach ($instances as $instance): ?>
            <li>
              Row <?php print $results[$instance]['row']; ?>: <?php print $url; ?>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $back_to_top_link; ?>
  <?php endif; ?>

  <?php if ($failure_count): ?>
    <div class="panel panel-default" id="errors">
      <div class="panel-heading">
        <h3 class="panel-title">Errors</h3>
      </div>
      <table id="failure" class="table table-striped table-bordered">
        <thead>
        <tr>
          <th>Original URL</th>
          <th>Expected URL</th>
          <th>HTTP response</th>
          <th>Actual URL</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($failures as $failure): ?>
          <tr>
            <td><?php print $failure['original']; ?></td>
            <td><?php print $failure['expected']; ?></td>
            <td><?php print $failure['http_code']; ?></td>
            <td><?php print $failure['actual']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php print $back_to_top_link; ?>
  <?php endif; ?>

  <?php if ($success_count): ?>
    <div class="panel panel-default" id="successes">
      <div class="panel-heading">
        <h3 class="panel-title">Successes</h3>
      </div>
      <table id="success" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>Original URL</th>
            <th>Expected URL</th>
            <th>HTTP response</th>
            <th>Actual URL</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($successes as $success): ?>
          <tr>
            <td><?php print $success['original']; ?></td>
            <td><?php print $success['expected']; ?></td>
            <td><?php print $success['http_code']; ?></td>
            <td><?php print $success['actual']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php print $back_to_top_link; ?>
  <?php endif; ?>
<?php } ?>
</div>
</body>
</html>
