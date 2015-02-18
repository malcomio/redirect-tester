<?php
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
 * @param $input
 */
function print_r_clean($input) {
  echo '<pre>';
  print_r($input);
  echo '</pre>';
}

/**
 * Prepare a curl resource.
 *
 * @param $proxy
 * @return resource
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
 * Visit a URL.
 *
 * @param $curl
 * @param string $url
 * @param bool $proxy
 *
 * @return array
 *   Array containing the
 *     url
 *     status code
 */
function visit_url($curl, $url, $proxy = FALSE) {
  $result = array();
  if (substr($url, 0, 4) == "http") {
    curl_setopt($curl, CURLOPT_URL, $url);
    $response = @curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($status === HTTP_STATUS_MOVED_PERMANENTLY || $status === HTTP_STATUS_FOUND) {
      preg_match("@https?://([-\w\.]+)+(:\d+)?(/([\w/_\-\.]*(\?\S+)?)?)?@", $response, $matches);
      $result['target'] = $matches[0];
    }
  }
  $result['httpcode'] = $status;
  $result['url'] = $url;

  return $result;
}

/**
 * Convert an array to CSV output.
 *
 * @param array $array
 * @return null|string
 */
function array2csv(array &$array) {
  if (count($array) == 0) {
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
 * @param $filename
 */
function download_send_headers($filename) {
  // disable caching
  $now = gmdate("D, d M Y H:i:s");
  header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
  header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
  header("Last-Modified: {$now} GMT");

  // force download
  header("Content-Type: application/force-download");
  header("Content-Type: application/octet-stream");
  header("Content-Type: application/download");

  // disposition / encoding on response body
  header("Content-Disposition: attachment;filename={$filename}");
  header("Content-Transfer-Encoding: binary");
}


/**
 * Output the results as a CSV.
 */
if (!empty($_POST['csv_output'])) {

  $results = unserialize($_POST['results']);

  download_send_headers("redirect-test-results_" . date("Y-m-d") . ".csv");
  echo array2csv($results);
  die();
}


$form = '<form method="post" action="index.php" enctype="multipart/form-data">
<div class="panel panel-default">
  <div class="panel-body">
  <div class="form-group">
          <label for="proxy">Proxy</label>
          <input type="text" name="proxy" value=""/>
</div>
<div class="form-group">
          <label for="csv_input">Upload a CSV file</label>
          <input type="file" name="csv_input" class="input-medium"/>
          </div>

        <input type="submit" class="btn"/>
</div>
</div>
      </form>';


?>
<html>
  <head>
    <title>URL Redirection Tester</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">
  </head>
  <body>
<div class="container">

    <h1>Redirection checker</h1>

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
  while ($row = fgetcsv($file)) {
    $original_url = $row[0];
    $expected_url = $row[1];

    $parsed_original = parse_url($original_url);
    $parsed_expected = parse_url($expected_url);

    // Is there a URL to check?
    if (array_key_exists('scheme', $parsed_original)) {
      $visit = visit_url($curl, $original_url, $proxy);

      $actual_url = '';

      $result = array(
        'original' => $original_url,
        'httpcode' => $visit['httpcode'],
      );

      switch ($visit['httpcode']) {
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
          break;
      }

      // Deal with multiple redirections.
      while ($visit['httpcode'] == HTTP_STATUS_MOVED_PERMANENTLY) {
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

  curl_close($curl);
  $result_count = count($results);
  $success_count = count($successes);
  $failure_count = count($failures);
  ?>

  <?php if ($result_count) : ?>
        <div class="panel panel-default">
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
    <input type="hidden" name="results" value="<?php print htmlentities(serialize($results)); ?>"/>
    <input type="submit" class="btn" value="Output as CSV"/>
  </form>

      <?php if ($failure_count): ?>
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">Errors</h3>
          </div>
          <table id="failure" class="table table-striped table-bordered">
            <thead>
            <th>Original URL</th>
            <th>Expected URL</th>
            <th>HTTP response</th>
            <th>Actual URL</th>
            </thead>
            <tbody>
            <?php foreach ($failures as $failure): ?>
              <tr>
                <td><?php print $failure['original']; ?></td>
                <td><?php print $failure['expected']; ?></td>
                <td><?php print $failure['httpcode']; ?></td>
                <td><?php print $failure['actual']; ?></td>
              </tr>
               <?php endforeach; ?>
              </tbody>
            </table>
          </div>

      <?php endif; ?>
      <?php if ($success_count): ?>
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">Successes</h3>
        </div>
        <table id="success" class="table table-striped table-bordered">

          <thead>
          <th>Original URL</th>
          <th>Expected URL</th>
          <th>HTTP response</th>
          <th>Actual URL</th>
          </thead>
          <tbody>
          <?php foreach ($successes as $success): ?>
            <tr>
              <td><?php print $success['original']; ?></td>
              <td><?php print $success['expected']; ?></td>
              <td><?php print $success['httpcode']; ?></td>
              <td><?php print $success['actual']; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>

      <?php
  endif;
}
?>
    </div>
  </body>
</html>
