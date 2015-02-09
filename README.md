redirect-tester
===============

This is a PHP script for testing large numbers of automated redirects, e.g. in a set of .htaccess rules.


## Usage

1. Clone this repo into a directory on your local testing web server.
1. Set up a CSV file listing the original URLs and the URLs you expect them to redirect to - see the [example CSV file provided](example.csv)
1. Browse to the place where you've cloned the repo, e.g. http://localhost/redirect-tester/
1. Upload your CSV file, and submit the form
1. Once the script has finished, it will give you a count and a list of passes and fails