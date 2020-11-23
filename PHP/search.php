<?php

// This file will not check whether there is an entry that already exists with the same base_url.
// Prior logic must determine that this site is not currently within the database.

/////////////////////////
// PRE-INITIALIZATION //
///////////////////////

// Begin timer
$begin = round(microtime(true) * 1000);
set_time_limit(120);

// Override PHP.ini so that errors do not display on browser.
ini_set('display_errors', 0);

////////////////////////
// CLASS DEFINITIONS //
//////////////////////

// This contains the search phrase typed by the user and the url searched from.
class Phrase {
    protected $phrase;
    protected $url;

    public function __construct($phrase, $url) {
        $this->phrase = $phrase;
        $this->url = $url;
    }

    public function get_phrase() {
        return $this->phrase;
    }

    public function set_phrase($new_phrase) {
        $this->phrase = $new_phrase;
    }

    public function get_url() {
        return $this->url;
    }

    public function set_url($new_url) {
        $this->url = $new_url;
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

$agent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.370.0 Safari/533.4';

// Get data from the POST sent from the fetch API
$raw = trim(file_get_contents('php://input'));
$url = json_decode($raw)->url;
$phrase = json_decode($raw)->phrase;

// Remove unnecessary characters and explode phrase into seperate terms
$phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]);
$terms = explode(' ', $phrase);

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
  'time_taken' => 0,
  'got_sitemap' => false,
  'got_pages' => false,
  'inserted_into_sites' => false,
  'found_site_id' => false,
  'inserted_into_pages' => false,
  'inserted_into_keywords' => false,
  'inserted_into_contents' => false,
  'curl_error' => NULL,
  'pdo_error' => NULL,
  'db_error' => NULL,
  'misc' => NULL
];

//////////////////////
// SEARCH DATABASE //
////////////////////

// Get credentials for database
$rawCreds = file_get_contents("../credentials.json");
$creds = json_decode($rawCreds);

$username = $creds->username;
$password = $creds->password;
$serverIp = $creds->server_ip;
$dbname = $creds->database_name;
$dsn = "mysql:dbname=".$dbname.";host=".$serverIp;

try {
    if ($response['got_sitemap'] !== true) {
        throw new Exception("Failed to retrieve sitemap. Can't create PDO instance.");
    }
    else if ($response['got_pages'] !== true) {
        throw new Exception("Failed to retrieve pages. Can't create PDO instance.");
    }

    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Errors placed in "S:\Program Files (x86)\XAMPP\apache\logs\error.log"
} 
catch (PDOException $e) {
    //echo 'Connection failed: ' . $e->getMessage();
    $response['pdo_error'] = 'Connection failed: ' . $e->getMessage();
} 
catch(Exception $e) {
    $response['pdo_error'] = $e->getMessage();
}

try {
    if (!isset($pdo)) {
        throw new Exception("PDO instance is not defined.");
    }

    // Grab relevant site_id from recent call
    $pdo->beginTransaction();
    $sql = 'SELECT site_id FROM sites WHERE url = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$url]);
    $sql_res = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
    $site_id = $sql_res[0];

    // Search through pages for all site of specific site.
    $sql = 'SELECT page_id FROM keywords WHERE keyword = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$url]);
    $sql_res = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
    $site_id = $sql_res[0];
} 
catch (Exception $e) {
    // One of our database queries have failed.
    // Print out the error message.
    //echo $e->getMessage();
    $response['db_error'] = $e->getMessage();
    // Rollback the transaction.
    if (isset($pdo)) {
        $pdo->rollBack();
    }
}

// Monitor program performance using this timer
$end = round(microtime(true) * 1000);
$response['time_taken'] = $end - $begin;

// Send a response back to the client.
echo json_encode($response);

// Input: String
// Output: String containing only letters and numbers (ASCII)
// Options: [ 'symbols' => bool, 'lower' => bool, 'upper' => bool]. True indicates to remove.
// Removes unknown and unwanted symbols from a given string.
function sanitize($str, $options = ['symbols' => false, 'lower' => false, 'upper' => false]) {
    $symbols_reg = '\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E';
    $lower_reg = '\x61-\x7A';
    //echo $lower_reg;
    $upper_reg = '\x41-\x5A';
    $regexp = '/[\x00-\x1F';
  
    /*if ($options['symbols'] && $options['upper'] && $options['lower']) {
      $regexp = '/[\x00-\x1F\x80-\xFF]/'
    }*/
  
    if ($options['symbols']) {
      $regexp .= $symbols_reg;
    }
    if ($options['lower']) {
      $regexp .= $lower_reg;
    }
    if ($options['upper']) {
      $regexp .= $upper_reg;
    }
  
    $regexp .= '\x80-\xFF]/';
  
    $str = strtolower($str);
    //echo '/[\x00-\x1F\x21-\x2F\x3A-\x60\x7B-\x7E\x80-\xFF]/';
    //echo $regexp . "\n";
    return preg_replace($regexp, '', $str); // Remove unwanted characters based on the values in the options array.
  }