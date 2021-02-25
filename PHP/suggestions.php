<?php
/////////////////////////
// PRE-INITIALIZATION //
///////////////////////

// Begin timer
$begin = round(microtime(true) * 1000);
set_time_limit(120);

// Override PHP.ini so that errors do not display on browser.
error_reporting(E_ALL);
ini_set('display_errors', 1);
//ini_set('display_errors', 0);

// Import necessary classes.
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__.'\\PHP\\classes\\classes.php');

// Import necessary functions.
require_once(__ROOT__.'\\PHP\\functions\\functions.php');
require_once(__ROOT__.'\\PHP\\functions\\suggestions_functions.php');

/////////////////////
// INITIALIZATION //
///////////////////

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'db_error' => NULL,
    'pdo_error' => NULL,
    'phrase' => NULL,
    'time_taken' => NULL,
    'suggestions' => NULL,
    'url' => NULL,
];

$url = null;
$phrase = null;
$limit = null;
$site_id = null;
$is_valid_get_request = isset($_GET) && isset($_GET['phrase']) && isset($_GET['url']) && isset($_GET['limit']) && !empty($_GET);

if ($is_valid_get_request) {
    // Trim and filter/sanitize the $_GET string data before formatting.
    $phrase = strtolower( sanitize( trim($_GET['phrase']), ['symbols' => false, 'lower' => false, 'upper' => false] ) );
    $url = filter_var( trim($_GET['url']), FILTER_SANITIZE_URL );
    $limit = filter_var( trim($_GET['limit']), FILTER_SANITIZE_NUMBER_INT );

    //array_filter($_GET, 'trim_value'); // the data in $_GET is trimmed
    $phrase = str_to_phrase($phrase); // Turn the string into a Phrase object
    $url = format_url($url); // Format the url which was recieved so that it does not end in '/'

    $response['phrase'] = $phrase;
    $response['url'] = $url;
}

/////////////////////////////////
// PREPARE TO ACCESS DATABASE //
///////////////////////////////

// Get credentials for database
$raw_credentials = file_get_contents("../credentials.json");
$credentials = json_decode($raw_credentials);
$pdo = create_pdo($credentials);

//////////////////////
// ACCESS DATABASE //
////////////////////
try {
    if (is_string($pdo)) {
        // Return PDO error
        $response['pdo_error'] = $pdo;
        throw new Exception("PDO error.");
    }
    if (!$is_valid_get_request) {
        throw new Exception("The request method used is either not a GET request or a parameter is missing. Parameters include: (string) phrase, (string) url, (int) limit).");
    }
    
    // Grab relevant site_id from recent call
    $sql = 'SELECT site_id FROM sites WHERE url = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$url]);
    $sql_res = $statement->fetch(); // Returns an array of *indexed and associative results. Indexed is preferred.

    // Check existence of site in database
    if ($sql_res) {
        $site_id = $sql_res['site_id'];
        $response['site_exists'] = true;
    }
    else {
        throw new Exception("Site not found in database.");
    }

    // Retrieve all previous searches that contain the currently typed phrase as a substring.
    $response['suggestions'] = create_suggestions_from_history($phrase, $pdo, $site_id, $limit);
} 
catch (Exception $e) {
    // One of our database queries have failed.
    // Print out the error message.
    $response['db_error'] = $e->getMessage();
}

// Monitor program performance using this timer
$end = round(microtime(true) * 1000);
$response['time_taken'] = $end - $begin;

// Send a response back to the client.
echo json_encode($response);
?>