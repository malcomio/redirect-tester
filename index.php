<?php

set_time_limit(0);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

function print_r_clean($input) {
  echo '<pre>';
  print_r($input);
  echo '</pre>';
}

function visit_url($url, $proxy = FALSE) {
  $result = array();
  if (substr($url, 0, 4) == "http") {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_NOBODY, TRUE);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);

    if ($proxy) {


      curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
      curl_setopt($curl, CURLOPT_PROXY, $proxy);
    }
    $response = @curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);


    if ($status === 301 || $status === 302) {
      preg_match("@https?://([-\w\.]+)+(:\d+)?(/([\w/_\-\.]*(\?\S+)?)?)?@", $response, $matches);
      $result['target'] = $matches[0];
    }
  }
  $result['httpcode'] = $status;
  $result['url'] = $url;

  return $result;
}

function visit($url, &$result = NULL) {
  $agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_USERAGENT, $agent);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//  curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
//  curl_setopt($curl, CURLOPT_PROXY, '10.23.12.100:8080');

  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
  curl_setopt($curl, CURLOPT_VERBOSE, FALSE);
  curl_setopt($curl, CURLOPT_TIMEOUT, 3);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($curl, CURLOPT_SSLVERSION, 3);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

  session_write_close();

  $page = curl_exec($curl);
  $error = curl_error($curl);
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  $curl_info = curl_getinfo($curl);
  print_r_clean($curl_info);

  curl_close($curl);

  $actual_url = 'testing';

  $result = array(
//    'page' => $page,
    'error' => $error,
    'httpcode' => $httpcode,
    'actual' => $actual_url,
  );

  return $result;
}

?>
<html>
<head>
  <title>URL Redirection Tester</title>
  <link
    href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/css/bootstrap-combined.min.css"
    rel="stylesheet">
  <link
    href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/css/bootstrap-combined.min.css"
    rel="stylesheet">
</head>
<body>
<div class="row">
  <div class="span8 offset2">
    <h1>Redirection checker</h1>
    <?php if (!array_key_exists('csv', $_FILES)) : ?>

    <form method="post" action="index.php" enctype="multipart/form-data">
      <label for="csv">Upload a CSV file</label>
      <input type="file" name="csv" class="input-medium" />
      <input type="submit" class="btn" />
    </form>

    <?php else : ?>
    <?php
    $file = fopen($_FILES['csv']['tmp_name'], 'r');

    $results = $successes = $failures = array();

    $count_200 = $count_301 = 0;

    $proxy = '10.23.12.100:8080';

    while ($row = fgetcsv($file)) {
      $original_url = $row[0];
      $expected_url = $row[1];

      $parsed_original = parse_url($original_url);
      $parsed_expected = parse_url($expected_url);

      // is there a URL to check?
      if (array_key_exists('scheme', $parsed_original)) {
        $visit = visit_url($original_url, $proxy);

        $actual_url = '';

        $result = array(
          'original' => $original_url,
          'httpcode' => $visit['httpcode'],
        );

        switch ($visit['httpcode']) {
          case '200':
            $count_200++;
            $actual_url = $visit['url'];
            break;
          case '301':
            $count_301++;
            $actual_url = $visit['target'];
            break;
        }

        $result['actual'] = $actual_url;

        // do we expect a particular URL?
        if (array_key_exists('scheme', $parsed_expected)) {
          $result['expected'] = $expected_url;

          if ($expected_url == $actual_url) {
            $successes[] = $result;
          } else {
            $failures[] = $result;
          }
        }
        $results[] = $result;
      }
    }

    $result_count = count($results);
    $success_count = count($successes);
    $failure_count = count($failures);
    ?>
    <?php if ($result_count) : ?>
      <table>
        <caption>Summary</caption>
        <thead>
        <tr>
          <th>URLs checked</th>
          <th>200</th>
          <th>301</th>
          <?php if ($success_count) : ?>
          <th>Successes</th>
          <?php endif; ?>
          <?php if ($failure_count): ?>
          <th>Failures</th>
          <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td><?php print $result_count; ?></td>
          <td><?php print $count_200; ?></td>
          <td><?php print $count_301; ?></td>
          <?php if ($success_count) : ?>
          <td><?php print $success_count; ?></td>
          <?php endif; ?>
          <?php if ($failure_count): ?>
          <td><?php print $failure_count; ?></td>
          <?php endif; ?>
        </tr>
        </tbody>
      </table>
      <?php endif; ?>
    <?php if ($failure_count): ?>
      <table>
        <caption>Errors</caption>
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
      <?php endif; ?>
    <a href="index.php">Start again</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
