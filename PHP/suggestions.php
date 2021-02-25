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

////////////////////////
// CLASS DEFINITIONS //
//////////////////////

// A Suggestion is an alternate Phrase
class Suggestion {
    public $original; // Phrase Object
    public $suggestion; // Phrase Object
    public $distance; // Total distance between the two Phrase objects.
    
    public function __construct(Phrase $original, Phrase $suggestion) {
        $this->original = $original;
        $this->suggestion = $suggestion;

        // Calculate the total distance between the two Phrases.
        $total = 0;
        for ($i = 0; $i < $original->length(); $i++) {
            $total += levenshtein($original->get_keyword($i)->get_text(), $suggestion->get_keyword($i)->get_text());
        }
        $this->distance = $total;
    }

    public function get_original_phrase() {
        return $this->original;
        //$phrase = "";
        //foreach ($this->keywords as $keyword) {
        //    $phrase .= $keyword . " ";
        //}
        //return substr($phrase, 0, -1); // Remove the space at the end.*/
    }
    public function get_suggested_phrase() {
        return $this->suggestion;
    }
    public function set_suggested_phrase(Phrase $suggestion) {
        $this->suggestion = $suggestion;
    }
    public function get_total_distance() {
        return $this->distance;
    }
}

class Phrase {
    public $keywords; // Array of Keyword objects
    public $text;
    
    public function __construct(array $keywords) {
        $this->keywords = $keywords;
        $this->text = $this->to_string(); // For some reason, this does not work. Use set_text as a temporary solution.
    }

    public function to_string() {
        $phrase = "";
        foreach ($this->keywords as $keyword) {
            $phrase .= $keyword->get_text() . " ";
        }
        return substr($phrase, 0, -1); // Remove the space at the end.
    }
    public function set_phrase(array $keywords) {
        $this->keywords = $keywords;
        $this->text = $this->to_string(); // For some reason, this does not work. Use set_text as a temporary solution.
    }

    function set_text($text) {
        $this->text = $text;
    }

    public function get_keyword(int $index) {
        return $this->keywords[$index];
    }
    public function get_all_keywords() {
        return $this->keywords;
    }
    public function set_keyword($keyword, $index) {
        $this->keywords[$index] = $keyword;
    }

    public function length() {
        return count($this->keywords);
    }

    public function has_misspelling() {
        foreach ($this->keywords as $keyword) {
            if ($keyword->is_misspelled()) {
                return true;
            }
        }
        return false;
    }
}

// A Prediction is an alternate to Keyword
class Prediction {
    public $original; // Keyword object
    public $prediction; // Keyword object
    public $distance;
    public $index;

    public function __construct(Keyword $original, Keyword $prediction) {
        $this->original = $original; // Keyword object
        $this->prediction = $prediction;
        $this->index = $original->get_index();
        $this->distance = levenshtein($original->get_text(), $prediction->get_text());
    }

    public function get_original() {
        return $this->original;
    }

    public function get_prediction() {
        return $this->prediction;
    }

    public function get_index() {
        return $this->index;
    }

    public function get_distance() {
        return $this->distance;
    }
}

class Keyword {
    //public $original; // Original term that this keyword references.
    //protected $pages_found;
    public $text;
    public $index; // Index of the term which this keyword references.
    public $is_misspelled; // Flag for whether the original keyword is misspelled.
    public $has_symbol;
    //public $has_suggestion;
    //public $suggestion_distance; // Levenshtein distance between the original term and the suggested term.
    protected $max; // Maximum dupe_totals of this keyword in the database.

    public function __construct(string $text, int $index) {
        $this->text = $text;
        $this->index = $index;
        $this->max = NULL;
        $this->is_misspelled = false;
    }

    public function get_index() {
        return $this->index;
    }

    public function get_text() {
        return $this->text;
    }

    public function set_text(string $new_keyword) {
        $this->text = $new_keyword;
    }

    public function is_misspelled(bool $bool = null) {
        if ($bool !== null) {
            $this->is_misspelled = $bool;
        }
        return $this->is_misspelled;
    }

    public function has_symbol(bool $bool = null) {
        if ($bool !== null) {
            $this->has_symbol = $bool;
        }
        return $this->has_symbol;
    }

    public function get_max() {
        return $this->max;
    }

    public function set_max(int $new_max) {
        $this->max = $new_max;
    }

    // If the max is set, output a relevance score.
    // If the max is not set, output 0;
    public function relevance(int $dupe_total) {
        if (isset($this->max)) {
            return ceil(($dupe_total / $this->max) * 100);
        }
        else {
            return 0;
        }
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

$raw = trim(file_get_contents('php://input'));
$url = format_url(json_decode($raw)->url);
$phrase = trim(json_decode($raw)->phrase); // User's input into the search bar. This phrase will get replaced after spellchecking is complete.
$phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]); // Remove unnecessary characters.
$phrase = str_to_phrase($phrase);
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
    /*$sql = "SELECT search_phrase,COUNT(search_phrase) AS times_searched FROM searches WHERE site_id = ? AND INSTR(search_phrase, ?)>0 AND INSTR(search_phrase, ?)<=" . strlen($phrase) . " GROUP BY search_phrase ORDER BY times_searched DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase, $phrase]);
    $suggestions = $statement->fetchAll();
    $suggestions = array_slice($suggestions, 0, $limit); // Ensure that we have at most $limit suggestions
    
    for ($i = 0; $i < count($suggestions); $i++) {
        $suggestions[$i] = $suggestions[$i]['search_phrase'];
    }*/
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

function str_to_phrase(string $str) {
    $tokens = explode(' ', $str);
    $keywords = [];
    foreach ($tokens as $index => $token) {
        $keywords[$index] = new Keyword($token, $index);
    }
    return new Phrase($keywords);
}

function create_suggestions_from_history(Phrase $phrase, PDO $pdo, int $site_id, int $limit = 10) {
    $suggestions = [];
    // Find all previous searches that contain the currently typed phrase
    $sql = "SELECT search_phrase,COUNT(search_phrase) AS times_searched FROM searches WHERE site_id = ? AND INSTR(search_phrase, ?)>0 AND INSTR(search_phrase, ?)<=" . strlen($phrase->to_string()) . " GROUP BY search_phrase ORDER BY times_searched DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase->to_string(), $phrase->to_string()]);
    $suggestions = $statement->fetchAll();
    $suggestions = array_slice($suggestions, 0, $limit); // Ensure that we have at most $limit suggestions
    
    foreach ($suggestions as $i => $suggestion) {
        // String to Phrase conversion
        $terms = explode(' ', $suggestion['search_phrase']);
        $keywords = [];
        foreach ($terms as $j => $term) {
            $keywords[] = new Keyword($term, $j);
        }
        $new_phrase = new Phrase($keywords);
        $suggestions[$i] = new Suggestion($phrase, $new_phrase);
    }
    return $suggestions;
}
?>