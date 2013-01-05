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
      <label for="proxy">Proxy</label>
      <input type="text" name="proxy" />
      <label for="csv">Upload a CSV file</label>
      <input type="file" name="csv" class="input-medium" />
      <input type="submit" class="btn" />
    </form>

    <?php else : ?>
    <?php
    $file = fopen($_FILES['csv']['tmp_name'], 'r');

    $results = $successes = $failures = array();

    $count_200 = $count_301 = $count_404 = 0;

    $proxy = $_POST['proxy'];

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
          case '404':
            $count_404++;
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
      <table class="table table-striped table-bordered">
        <caption>Summary</caption>
        <thead>
        <tr>
          <th>URLs checked</th>
          <th>200</th>
          <th>301</th>
          <th>404</th>
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
          <td><?php print $success_count; ?></td>
          <?php endif; ?>
          <?php if ($failure_count): ?>
          <td><?php print $failure_count; ?></td>
          <?php endif; ?>
        </tr>
        </tbody>
      </table>
      <?php endif; ?>
    <a href="index.php">Start again</a>
    <?php if ($failure_count): ?>
      <table class="table table-striped table-bordered">
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
    <?php if ($success_count): ?>
      <table class="table table-striped table-bordered">
        <caption>Successes</caption>
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
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
</body>
</html>
