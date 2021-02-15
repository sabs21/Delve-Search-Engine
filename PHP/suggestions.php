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

/////////////////////
// INITIALIZATION //
///////////////////

$raw = trim(file_get_contents('php://input'));
$url = format_url(json_decode($raw)->url);
$phrase = trim(json_decode($raw)->phrase); // User's input into the search bar. This phrase will get replaced after spellchecking is complete.
$phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]); // Remove unnecessary characters.
$limit = json_decode($raw)->limit;
$site_id = NULL;

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'db_error' => NULL,
    'pdo_error' => NULL,
    'phrase' => $phrase,
    'time_taken' => NULL,
    'suggestions' => NULL,
    'url' => $url,
];

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

    // Find all previous searches that contain the currently typed phrase
    $sql = "SELECT search_phrase,COUNT(search_phrase) AS times_searched FROM searches WHERE site_id = ? AND INSTR(search_phrase, ?)>0 AND INSTR(search_phrase, ?)<=" . strlen($phrase) . " GROUP BY search_phrase ORDER BY times_searched DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase, $phrase]);
    $suggestions = $statement->fetchAll(); // Returns an array of *indexed and associative results. Indexed is preferred.

    /*foreach ($suggestions as $index => $suggestion) {
        if ($index < $limit) {

        }
        else {
            break;
        }
    }*/
    $suggestions = array_slice($suggestions, 0, $limit); // Ensure that we have at most $limit suggestions
    
    for ($i = 0; $i < count($suggestions); $i++) {
        $suggestions[$i] = $suggestions[$i]['search_phrase'];
        /*unset($suggestions[$i]['search_phrase']);
        unset($suggestions[$i]['times_searched']);
        unset($suggestions[$i][0]);
        unset($suggestions[$i][1]);*/
    }
    $response['suggestions'] = $suggestions;
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

// Input: URL received from the frontend.
//        (optional) Boolean that determines whether to end the url with a '/'
// Output: URL with or without a path.
// Ensures a given URL has (or doesnt have) a path.
function format_url($raw_url, $include_path = true) {
    $urlNoPath = $raw_url;
    $url = $raw_url;
    // Format the url which was recieved so that it does not end in '/'
    if ($url[strlen($url) - 1] === '/') {
        $urlNoPath = substr($url, 0, strlen($url) - 1);
        //$url .= '/';
    }
    else {
        $url .= '/'; 
    }

    if ($include_path) {
        return $url;
    }
    else {
        return $urlNoPath;
    }
}

// Input: Database credentials in object format.
// Output: PDO object or error message.
// Create a PDO object that's registered with the database.
function create_pdo($credentials) {
    $username = $credentials->username;
    $password = $credentials->password;
    $serverIp = $credentials->server_ip;
    $dbname = $credentials->database_name;
    $dsn = "mysql:dbname=".$dbname.";host=".$serverIp;

    // Create a new PDO instance
    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Errors placed in "S:\Program Files (x86)\XAMPP\apache\logs\error.log"
        return $pdo;
    } 
    catch (PDOException $e) {
        $error = 'Connection failed: ' . $e->getMessage();
        return $error;
    } 
    catch(Exception $e) {
        $error = $e->getMessage();
        return $error;
    }
}
?>