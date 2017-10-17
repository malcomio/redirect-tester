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

function main_processor() {
    $processor = new redirectTester(ifset($_POST, 'find'), ifset($_POST, 'replace'));
    $processor->curlSetup(ifset($_POST, 'proxy'), ifset($_POST, 'user'), ifset($_POST, 'password'));
    $processor->processCSVFile($_FILES['csv_input']['tmp_name']);

    $duplicate_originals = array_filter(array_count_values($processor->getOriginals()), 'more_than_1'); //@todo: replace more_than_1 with inline function.

    $invalid_originals = array_filter($processor->getOriginals(), 'invalid_url');
    $invalid_expecteds = array_filter($processor->getExpecteds(), 'invalid_url');

    $navigation_markup = generateNavigation($processor, $duplicate_originals, $invalid_originals, $invalid_expecteds);

    if ($processor->resultsCount) {
        outputSummaryTable($processor);

        if ($processor->find) {
            outputFindAndReplace($processor);
        }
    }

    outputButtons($navigation_markup, $processor);

    if (!empty($duplicate_originals)) {
        outputDuplicateOriginals($navigation_markup, $processor, $duplicate_originals);
    }

    if (!empty($invalid_originals)) {
        outputInvalidOriginals($navigation_markup, $processor, $invalid_originals);
    }

    if (!empty($invalid_expecteds)) {
        outputInvalidExpected($navigation_markup, $processor, $invalid_expecteds);
    }

    if ($processor->failureCount) {
        outputFailures($navigation_markup, $processor);
    }

    if ($processor->successCount) {
        outputSuccesses($navigation_markup, $processor);
    }
}

function outputSummaryTable($processor)
{
?>
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
          <?php if ($processor->successCount) : ?>
            <th>Successes</th>
          <?php endif; ?>
          <?php if ($processor->failureCount): ?>
            <th>Errors</th>
          <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td><?php print $processor->resultsCount; ?></td>
          <td><?php print $processor->count200; ?></td>
          <td><?php print $processor->count301; ?></td>
          <td><?php print $processor->count404; ?></td>
          <?php if ($processor->successCount) : ?>
            <td>
              <a href="#success">
                <?php print $processor->successCount; ?>
              </a>
            </td>
          <?php endif; ?>
          <?php if ($processor->failureCount): ?>
            <td>
              <a href="#failure">
                <?php print $processor->failureCount; ?>
              </a>
            </td>
          <?php endif; ?>
        </tr>
        </tbody>
      </table>
    </div>
<?php
}

function outputFindAndReplace($processor)
{
?>
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
              <td><?php print $processor->find; ?></td>
              <td><?php print $processor->replace; ?></td>
            </tr>
          </tbody>
        </table>
      </div>
<?php    
}

function outputButtons($navigation_markup, $processor)
{
?>
  <p>
    <a href="index.php" class="btn btn-success">Start again</a>
  </p>

  <form method="post">
    <input type="hidden" name="csv_output" value="true"/>
    <input type="hidden" name="results"
           value="<?php print htmlentities(serialize($processor->results)); ?>"/>
    <input type="submit" class="btn" value="Output as CSV"/>
  </form>

  <?php print $navigation_markup; ?>

  <?php
}

function generateNavigation($processor, $duplicate_originals, $invalid_originals, $invalid_expecteds)
{
    $back_to_top_link = '<a href="#">Back to top</a>';
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

    if ($processor->failureCount) {
        $navigation[] = '<a href="#errors">Errors</a>';
    }

    if ($processor->successCount) {
        $navigation[] = '<a href="#successes">Successes</a>';
    }

    $navigation_markup = '<ul>';
    foreach ($navigation as $link) {
        $navigation_markup .= '<li>' . $link . '</li>';
    }
    $navigation_markup .= '</ul>';

    return $navigation_markup;
}

function outputDuplicateOriginals($navigation_markup, $processor, $duplicate_originals)
{
?>
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
              $instances = array_keys($processor->getOriginals(), $original);
              foreach ($instances as $instance) {
                print '<li>Row ' . $processor->results[$instance]['row'] . '</li>';
              }
              ?>
            </ul>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $navigation_markup; ?>
<?php
}

function outputInvalidOriginals($navigation_markup, $processor, $invalid_originals)
{
?>
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
              Row <?php print $processor->results[$instance]['row']; ?>: <?php print $url; ?>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $navigation_markup; ?>
<?php
}

function outputInvalidExpected($navigation_markup, $processor, $invalid_expecteds)
{
?>
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
              Row <?php print $processor->results[$instance]['row']; ?>: <?php print $url; ?>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php print $navigation_markup; ?>
<?php
}

function outputFailures($navigation_markup, $processor)
{
?>
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
        <?php foreach ($processor->getFailures() as $failure): ?>
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
<?php
}

function outputSuccesses($navigation_markup, $processor)
{
?>
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
        <?php foreach ($processor->getSuccesses() as $success): ?>
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
<?php
}
