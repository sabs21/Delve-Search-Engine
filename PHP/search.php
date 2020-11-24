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

// Contains all results and page seperated results.
class ResultSet {
    protected $results;
    protected $pages;

    public function __construct($results) {
        $this->results = $results;
        $this->pages = array_chunk($results, 10);
    }

    public function get_results() {
        return $this->results;
    }

    public function set_results($new_results) {
        $this->results = $new_results;
    }

    public function get_page($num) {
        return $pages[$num];
    }

    public function get_all_pages() {
        return $pages;
    }
}

class RelevanceBin {
    protected $bins;
    
    public function __construct() {
        $this->bins = [];
    }

    public function get_bins() {
        return $this->bins;
    }

    // Each bin holds the relevancy score of a given page.
    // If a bin exists for the given page_id, then add the new value to the existing value. 
    // If a bin does not exist for the given page_id, create one and set its value.
    public function add_bin($page_id, $value) {
        $this->bins[$page_id] += $value;
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

// Format the url which was recieved so that it does not end in '/'
if ($url[strlen($url) - 1] == '/') {
    $url = substr($url, 0, strlen($url) - 1);
}

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
  'time_taken' => 0,
  'found_site_id' => false,
  'search_phrase' => NULL,
  'search_terms' => NULL,
  //'bins' => NULL,
  'search_results' => NULL,
  //'ordered_by_relevance' => NULL,
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

// Create a new PDO instance
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Errors placed in "S:\Program Files (x86)\XAMPP\apache\logs\error.log"
} 
catch (PDOException $e) {
    $response['pdo_error'] = 'Connection failed: ' . $e->getMessage();
} 
catch(Exception $e) {
    $response['pdo_error'] = $e->getMessage();
}

// Communicate with the database
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

    // Detect the success of pulling the site_id from the database.
    $response['found_site_id'] = true;

    // Remove unnecessary characters and explode phrase into seperate terms
    $phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]);
    $response['search_phrase'] = $phrase;
    $terms = explode(' ', $phrase);
    $response['search_terms'] = $terms;

    // Obtain results for each term in the search phrase
    $all_results = [];
    foreach ($terms as $term) {
        // Search through keywords for all pages which contain a matching keyword.
        $sql = 'SELECT page_id, dupe_count FROM keywords WHERE keyword = ? ORDER BY page_id DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute([$term]);
        $results = $statement->fetchAll(); // Returns an array of indexed and associative results. Indexed is preferred.

        // ResultSet object contains all results from the database.
        $result_set = new ResultSet($results);
        $all_results[] = $result_set;
    }

    //$response['misc'] = $all_results[0]->get_results();

    // Create a new array of bins which will hold the relevance score for each page.
    $bins = new RelevanceBin();

    // Calculate relevance by adding to bins which correspond to each page_id
    foreach ($all_results as $result_set) {
        $results = $result_set->get_results();
        foreach ($results as $result) {
            $bins->add_bin($result['page_id'], $result['dupe_count']);
        }
    }

    // Sort the pages by their relevance score
    $bins = $bins->get_bins();
    arsort($bins); // Sorted in descending order (most relevant to least relevant).
    $relevant_pages = $bins;
    //$response['ordered_by_relevance'] = $relevant_pages;

    // Put all array keys (aka page_id's) into a separate array.
    $page_ids = array_keys($relevant_pages);

    // Grab pages from the database in the order of page relevance.
    $relevant_urls = [];
    foreach ($page_ids as $page_id) {
        $sql = 'SELECT path FROM pages WHERE page_id = ' . $page_id;
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $results = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
        $relevant_urls[] = $url . $results[0];
    }

    $response['search_results'] = $relevant_urls;
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
    $upper_reg = '\x41-\x5A';
    $regexp = '/[\x00-\x1F';
  
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
    return preg_replace($regexp, '', $str); // Remove unwanted characters based on the values in the options array.
}

// Input: total_placeholders; The amount of '?' to go into each string like (?, ?, ..., ?)
//        total_values; The amount of (?, ?, ..., ?) strings to create for the PDO/SQL request.
// Output: A placeholder string that is used within sql queries for PDO.
// Generate a valid PDO placeholder string for some sql query.
function create_pdo_placeholder_str($total_placeholders, $total_values) {
    // Generate the PDO placeholder string to be repeated $total_values times.
    $placeholder_unit = '(';
    for ($i = 0; $i < $total_placeholders; $i++) {
      if ($i + 1 === $total_placeholders) {
        $placeholder_unit .= '?)';
      }
      else {
        $placeholder_unit .= '?, ';
      }
    }
  
    // Repeat the $placeholder_value a total of $total_values times.
    // This forms a correct PDO string which is placed after VALUES inside an sql statement.
    $pdo_str = '';
    for ($i = 0; $i < $total_values; $i++) {
      if ($i + 1 === $total_values) {
        $pdo_str .= $placeholder_unit;
      }
      else {
        $pdo_str .= $placeholder_unit . ',';
      }
    }
  
    return $pdo_str;
}