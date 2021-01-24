<?php
class rtOutput
{
    private function outputSummaryTable()
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
              <?php if ($this->processor->successCount) : ?>
                <th>Successes</th>
              <?php endif; ?>
              <?php if ($this->processor->failureCount): ?>
                <th>Errors</th>
              <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <tr>
              <td><?php print $this->processor->resultsCount; ?></td>
              <td><?php print $this->processor->count200; ?></td>
              <td><?php print $this->processor->count301; ?></td>
              <td><?php print $this->processor->count404; ?></td>
              <?php if ($this->processor->successCount) : ?>
                <td>
                  <a href="#success">
                    <?php print $this->processor->successCount; ?>
                  </a>
                </td>
              <?php endif; ?>
              <?php if ($this->processor->failureCount): ?>
                <td>
                  <a href="#failure">
                    <?php print $this->processor->failureCount; ?>
                  </a>
                </td>
              <?php endif; ?>
            </tr>
            </tbody>
          </table>
        </div>
    <?php
    }

    private function outputFindAndReplace()
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
                  <td><?php print $this->processor->find; ?></td>
                  <td><?php print $this->processor->replace; ?></td>
                </tr>
              </tbody>
            </table>
          </div>
    <?php    
    }

    private function outputButtons()
    {
    ?>
      <p>
        <a href="index.php" class="btn btn-success">Start again</a>
      </p>

      <form method="post">
        <input type="hidden" name="csv_output" value="true"/>
        <input type="hidden" name="results"
               value="<?php print htmlentities(serialize($this->processor->results)); ?>"/>
        <input type="submit" class="btn" value="Output as CSV"/>
      </form>

      <?php print $this->navigationMarkup; ?>

      <?php
    }

    private function outputDuplicateOriginals()
    {
    ?>
        <div class="alert alert-warning" role="alert" id="error-duplicates">
          <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
          <span class="sr-only">Error:</span>
          The input contains the following duplicate original URLs:
          <ul>
            <?php foreach ($this->duplicateOriginals as $original => $count): ?>
              <li>
                <?php print $original . ' : ' . $count . ' instances'; ?>
                <ul>
                  <?php
                  $instances = array_keys($this->processor->getOriginals(), $original);
                  foreach ($instances as $instance) {
                    print '<li>Row ' . $this->processor->results[$instance]['row'] . '</li>';
                  }
                  ?>
                </ul>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php print $this->navigationMarkup; ?>
    <?php
    }

    private function outputInvalidOriginals()
    {
    ?>
        <div class="alert alert-danger" role="alert" id="error-invalid-original">
          <span class="glyphicon glyphicon-exclamation-sign"
                aria-hidden="true"></span>
          <span class="sr-only">Error:</span>
          The input contains the following invalid original URLs:
          <ul>
            <?php foreach ($this->invalidOriginals as $url): ?>
              <?php
              $instances = array_keys($this->invalidOriginals, $url);
              foreach ($instances as $instance): ?>
                <li>
                  Row <?php print $this->processor->results[$instance]['row']; ?>: <?php print $url; ?>
                </li>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php print $this->navigationMarkup; ?>
    <?php
    }

    private function outputInvalidExpected()
    {
    ?>
        <div class="alert alert-danger" role="alert" id="error-invalid-expected">
          <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
          <span class="sr-only">Error:</span>
          The input contains the following invalid expected URLs:
          <ul>
            <?php foreach ($this->invalidExpecteds as $url): ?>
              <?php
              $instances = array_keys($this->invalidExpecteds, $url);
              foreach ($instances as $instance): ?>
                <li>
                  Row <?php print $this->processor->results[$instance]['row']; ?>: <?php print $url; ?>
                </li>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php print $this->navigationMarkup; ?>
    <?php
    }

    private function outputFailures()
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
            <?php foreach ($this->processor->getFailures() as $failure): ?>
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
        <?php print $this->navigationMarkup; ?>
    <?php
    }

    private function outputSuccesses()
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
            <?php foreach ($this->processor->getSuccesses() as $success): ?>
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
        <?php print $this->navigationMarkup; ?>
    <?php
    }

    private $processor = null;
    private $duplicateOriginals = null;
    private $invalidOriginal = null;
    private $invalidExpecteds = null;

    public function __construct($processor)
    {
        $this->processor = $processor;

        $this->duplicateOriginals = array_filter(array_count_values($processor->getOriginals()), 'more_than_1'); //@todo: replace more_than_1 with inline function.

        $this->invalidOriginals = array_filter($processor->getOriginals(), 'invalid_url');
        $this->invalidExpecteds = array_filter($processor->getExpecteds(), 'invalid_url');

        $this->navigationMarkup = $this->generateNavigation();
    }

    private function generateNavigation()
    {
        $back_to_top_link = '<a href="#">Back to top</a>';
        $navigation = array($back_to_top_link);

        if (!empty($this->duplicateOriginals)) {
            $navigation[] = '<a href="#error-duplicates">Duplicate originals</a>';
        }

        if (!empty($this->invalidOriginals)) {
            $navigation[] = '<a href="#error-invalid-original">Invalid original URLs</a>';
        }

        if (!empty($this->invalidExpecteds)) {
            $navigation[] = '<a href="#error-invalid-expected">Invalid expected URLs</a>';
        }

        if ($this->processor->failureCount) {
            $navigation[] = '<a href="#errors">Errors</a>';
        }

        if ($this->processor->successCount) {
            $navigation[] = '<a href="#successes">Successes</a>';
        }

        $navigation_markup = '<ul>';
        foreach ($navigation as $link) {
            $navigation_markup .= '<li>' . $link . '</li>';
        }
        $navigation_markup .= '</ul>';

        return $navigation_markup;
    }


    public function generate()
    {
        if ($this->processor->resultsCount) {
            $this->outputSummaryTable();

            if ($this->processor->find) {
                $this->outputFindAndReplace();
            }
        }

        $this->outputButtons();

        if (!empty($this->duplicateOriginals)) {
            $this->outputDuplicateOriginals();
        }

        if (!empty($this->invalidOriginals)) {
            $this->outputInvalidOriginals();
        }

        if (!empty($this->invalidExpecteds)) {
            $this->outputInvalidExpected();
        }

        if ($this->processor->failureCount) {
            $this->outputFailures();
        }

        if ($this->processor->successCount) {
            $this->outputSuccesses();
        }
    }
}
