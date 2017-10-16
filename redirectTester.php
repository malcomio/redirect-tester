<?php

function main_processor() {
  $back_to_top_link = '<a href="#">Back to top</a>';

  $file = fopen($_FILES['csv_input']['tmp_name'], 'r');

  $results = $successes = $failures = array();

  $count_200 = $count_301 = $count_404 = 0;

  $proxy = $_POST['proxy'];

  $user = $_POST['username'];
  $password = $_POST['password'];

  $curl = setup_curl($proxy, $user, $password);

  $row_number = 0;
  while ($row = fgetcsv($file)) {
    $row_number++;
    if (!empty($row[0]) && !empty($row[1])) {
      $original_url = trim($row[0]);
      $expected_url = trim($row[1]);

      if (!empty($_POST['find']) && !empty($_POST['replace'])) {
        $find = $_POST['find'];
        $replace = $_POST['replace'];

        $original_url = str_replace($find, $replace, $original_url);
        $expected_url = str_replace($find, $replace, $expected_url);
      }

      $parsed_original = parse_url($original_url);
      $parsed_expected = parse_url($expected_url);

      // Is there a URL to check?
      if (array_key_exists('scheme', $parsed_original)) {
        $visit = visit_url($curl, $original_url, $proxy);

        $actual_url = '';

        $result = array(
          'row' => $row_number,
          'original' => $original_url,
          'expected' => $expected_url,
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
        $result['http_code'] = $visit['http_code'];

        // Do we expect a particular URL?
        if (array_key_exists('scheme', $parsed_expected)) {
          if (url_matches($expected_url, $actual_url)) {
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

    <?php if (!empty($find)): ?>
      <div class="panel panel-default" id="find-replace">
        <div class="panel-heading">
          <h3 class="panel-title">Find and replace</h3>
        </div>
        <table class="table table-striped table-bordered">
          <thead>
            <th>Find</th>
            <th>Replace</th>
          </thead>
          <tbody>
            <tr>
              <td><?php print $find; ?></td>
              <td><?php print $replace; ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

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
  $navigation = array($back_to_top_link);

  if (!empty($duplicate_originals)) {
    $navigation[] = '<a href="#error-duplicates">Duplicate originals</a>';
  }

  if (!empty($invalid_originals)) {
    $navigation[] = '<a href="#error-invalid-original">Invalid original URLs</a>';
  }

  if (!empty($invalid_expecteds)) {
    $navigation[] = '<a href="#error-invalid-expected">Invalid expected URLs</a>';
  }

  if (!empty($failures)) {
    $navigation[] = '<a href="#errors">Errors</a>';
  }

  if (!empty($successes)) {
    $navigation[] = '<a href="#successes">Successes</a>';
  }

  $navigation_markup = '<ul>';
  foreach ($navigation as $link) {
    $navigation_markup .= '<li>' . $link . '</li>';
  }
  $navigation_markup .= '</ul>';

  print $navigation_markup;
  ?>

  <?php if (!empty($duplicate_originals)): ?>
    <div class="alert alert-warning" role="alert" id="error-duplicates">
      <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
      <span class="sr-only">Error:</span>
      The input contains the following duplicate original URLs:
      <ul>
        <?php foreach ($duplicate_originals as $original => $count): ?>
          <li>
            <?php print $original . ' : ' . $count . ' instances'; ?>
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
    <?php print $navigation_markup; ?>
  <?php endif; ?>

  <?php if (!empty($invalid_originals)): ?>
    <div class="alert alert-danger" role="alert" id="error-invalid-original">
      <span class="glyphicon glyphicon-exclamation-sign"
            aria-hidden="true"></span>
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
    <?php print $navigation_markup; ?>
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
    <?php print $navigation_markup; ?>
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
    <?php print $navigation_markup; ?>
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
    <?php print $navigation_markup; ?>
  <?php endif; ?>
<?php
}
