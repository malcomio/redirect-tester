<?php
/**
 * @file
 * Script for testing a list of http redirects.
 */

require_once('functions.php');
require_once('redirectTester.php');
require_once('rtOutput.php');

set_time_limit(0);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

define('HTTP_STATUS_OK', 200);
define('HTTP_STATUS_MOVED_PERMANENTLY', 301);
define('HTTP_STATUS_FOUND', 302);
define('HTTP_STATUS_NOT_FOUND', 404);

/**
 * Output the results as a CSV.
 */
if (!empty($_POST['csv_output'])) {
  output_csv_results($_POST['results']);
  die();
}

include "template_top.html";

if (!array_key_exists('csv_input', $_FILES)) {
    include "form.html";
} else {
    $processor = new redirectTester(ifset($_POST, 'find'), ifset($_POST, 'replace'));
    $processor->curlSetup(ifset($_POST, 'proxy'), ifset($_POST, 'user'), ifset($_POST, 'password'));
    $processor->processCSVFile($_FILES['csv_input']['tmp_name']);

    $output = new rtOutput($processor);
    $output->generate();
}

?>
</div>
</body>
</html>
