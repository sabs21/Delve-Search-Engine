<?php

// This file will not check whether there is an entry that already exists with the same base_url.
// Prior logic must determine that this site is not currently within the database.
//
// Search.php only accepts GET requests

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

// Consider replacing relevance array with a ScoreKeeper class.
class ScoreKeeper {
    public $scores;

    public function __construct() {
        $this->scores = [];
    }

    public function add_to_score($score, $page_id) {
        if (isset($this->scores[$page_id])) {
            $this->scores[$page_id] += $score;
        }
        else {
            $this->scores[$page_id] = $score;
        }
    }

    public function get_score($page_id) {
        return $this->scores[$page_id];
    }

    public function get_all_scores() {
        return $this->scores;
    }
}

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

class Result {
    public $page_id; // Unused for now
    public $url;
    public $title;
    public $snippets;
    //public $dupeTotals; // An array which holds every dupe_total for this given page
    public $relevance;

    public function __construct(int $page_id, string $url = NULL, string $title = NULL, int $relevance = 0) {
        $this->page_id = $page_id;
        $this->url = $url;
        $this->title = $title;
        $this->snippets = [];
        //$this->dupeTotals = [];
        $this->relevance = $relevance;
    }

    public function get_page_id() {
        return $this->page_id;
    }

    public function get_url() {
        return $this->url;
    }
    public function set_url($str) {
        $this->url = $str;
    }

    public function get_title() {
        return $this->title;
    }
    public function set_title(string $str) {
        $this->title = $str;
    }

    public function get_snippet($i) {
        return $this->snippets[$i];
    }
    public function get_all_snippets() {
        return $this->snippets;
    }
    // $str (String)
    // $from_page_content (Boolean)
    public function add_snippet(string $text, bool $from_page_content) {
        $this->snippets[] = ["text" => $text, "fromPageContent" => $from_page_content];
    }

    public function get_relevance() {
        return $this->relevance;
    }
    public function set_relevance(int $value) {
        $this->relevance = $value;
    }
}

// This dictionary is created from the website's keywords.
// The dictionary consists of sections seperated by each keyword's beginning letter.
// This allows for the Dictionary to retain the Section objects that are created.
class Dictionary {
    public $dictionary; // Array of Section objects.
    //public $section; // Letter which all words begin with.

    public function __construct($dictionary = []) {
        $this->dictionary = $dictionary;
    }

    // All dictionaries must be created before they are retrieved.
    public function add_section(Section $section) {
        $first_letter = $section->get_char();
        $this->dictionary[$first_letter] = $section;
    }

    public function get_section($first_letter) {
        return $this->dictionary[$first_letter];
    }

    public function has_section($first_letter) {
        return isset($this->dictionary[$first_letter]);
    }

    public function get_section_from_char(string $char) {
        // Check to see if this character is a letter.
        $letter_regex = "/[A-z]/";
        $is_letter = preg_match($letter_regex, $char);
        if ($is_letter) {
            return $char;
        }
        else {
            // Check to see if this character is a number.
            $number_regex = "/[0-9]/";
            $is_number = preg_match($number_regex, $char);
            if ($is_number) {
                return "digit";
            }
            return false; // This keyword does not start with a letter nor a number. Return false to signal that this section cannot exist.
        }
    }
}

// This Section (of a Dictionary) is a collection of the website's keywords.
// The purpose of this over a traditional dictionary is better suggestions.
class Section {
    public $section;
    public $char; // Character which all words begin with.

    public function __construct() {
        $this->section = null;
        $this->char = null;
    }

    // All dictionaries must be created before they are retrieved.
    public function create_section($pdo, $site_id, $char) {
        try {
            $this->char = $char;
            $sql = 'SELECT DISTINCT keyword FROM keywords_' . $char . ' WHERE site_id = ? ORDER BY keyword ASC;';
            $statement = $pdo->prepare($sql);
            $statement->execute([$site_id]);
            $this->section = $statement->fetchAll();
            return true;
        } 
        catch (Exception $e) {
            // Our database queries has failed.
            // Print out the error message.
            //$response['dbError'] = $e->getMessage();
            return false;
        }
    }

    public function get_char() {
        return $this->char;
    }

    public function word_at($index) {
        return $this->section[$index]['keyword'];
    }

    public function length() {
        return count($this->section);
    }

    // Input: key is the word we're searching for
    // Output: Array containing a flag stating if an exact match was found, and the index of the match.
    // Perform a binary search for a given term based on a word.
    public function search(string $key) {
        //$arr = parent::get_section();
        $l = 0;
        $h = $this->length();
        $mid = ceil($l + ($h - $l) / 2); 

        while ($h >= $l) {
            // If the element is present at the middle itself 
            if ($this->word_at($mid) === $key) {
                return floor($mid); 
            }
    
            // If element is smaller than mid, then 
            // it can only be present in left subarray 
            if ($this->word_at($mid) > $key) {
                $h = $mid - 1;
            }
            else {
                // Else the element can only be present in right subarray 
                $l = $mid + 1;
            }

            $mid = ceil($l + ($h - $l) / 2);
        }
    
        // We reach here when element is not present in array 
        return false;
    }

    // Input: word (String)
    //        term_index (Integer)
    // Output: Array of Keyword objects
    // Find similar words to the word given.
    public function similar_to(Keyword $keyword) {
        $predictions = [];
        $max_distance = 2; // Maximum allowed levenshtein distance
        foreach ($this->section as $entry) {
            $distance = levenshtein($keyword->get_text(), $entry['keyword']);
            if ($distance <= $max_distance) {
                $good_entry = new Keyword($entry['keyword'], $keyword->get_index());
                $predictions[] = new Prediction($keyword, $good_entry);
            }
        }
        return $predictions;
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'dbError' => NULL,
    'page' => NULL, //$page_to_return + 1,
    'pdoError' => NULL,
    'phpError' => NULL,
    'phrase' => NULL,
    'predictions' => NULL,
    'results' => NULL,
    'suggestions' => NULL,
    'timeTaken' => NULL,
    'totalPages' => NULL,
    'totalResults' => NULL,
    'url' => NULL //$url
];

$save_search = true;
$url = null;
$phrase = null;
$page_to_return = null;
$site_id = null;
$filter_symbols = null;

if (isset($_GET) && !empty($_GET)) {
    $filter_symbols = filter_var(trim($_GET['filter_symbols']), FILTER_VALIDATE_BOOLEAN);
    // Trim and filter/sanitize the $_GET string data before formatting.
    if ($filter_symbols) {
        $phrase = sanitize( trim($_GET['phrase']), ['symbols' => false, 'lower' => false, 'upper' => false] );
    }
    else {
        // Filter out symbols if search is not forced.
        $phrase = sanitize( trim($_GET['phrase']), ['symbols' => true, 'lower' => false, 'upper' => false] );
    }
    $url = filter_var( trim($_GET['url']), FILTER_SANITIZE_URL );
    $page_to_return = filter_var( trim($_GET['page']), FILTER_SANITIZE_NUMBER_INT );

    //array_filter($_GET, 'trim_value'); // the data in $_GET is trimmed
    $phrase = str_to_phrase($phrase); // Turn the string into a Phrase object
    $url = format_url($url); // Format the url which was recieved so that it does not end in '/'
    $page_to_return = $page_to_return - 1; // This value will be used as an array index, so we subtract 1.
    
    $response['filtered_symbols'] = $filter_symbols;
    $response['phrase'] = $phrase;
    $response['url'] = $url;
    $response['page'] = $page_to_return + 1;
}

/////////////////////////////////
// PREPARE TO SEARCH DATABASE //
///////////////////////////////

// Get credentials for database
$raw_credentials = file_get_contents("../credentials.json");
$credentials = json_decode($raw_credentials);
$pdo = create_pdo($credentials);

/////////////////////////////
// DATABASE COMMUNICATION //
///////////////////////////
try {
    if (!isset($pdo)) {
        throw new Exception("PDO instance is not defined.");
    }
    else if (is_string($pdo)) {
        // Return PDO error
        $response['pdoError'] = $pdo;
        throw new Exception("PDO error.");
    }
    else if (!isset($_GET) || empty($_GET)) {
        $response['phpError'] = $pdo;
        throw new Exception("Request method used to call search.php is not a GET request.");
    }
    else if ($phrase->length() <= 0) {
        throw new Exception("No keywords to search for. This may be caused by a blank search or due to serverside input sanitization removing symbols.");
    }

    // Grab relevant site_id from recent call
    $sql = 'SELECT site_id FROM sites WHERE url = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$url]);
    $sql_res = $statement->fetch(); // Returns an array of *indexed and associative results. Indexed is preferred.

    // Check existence of site in database
    if ($sql_res) {
        $site_id = $sql_res['site_id'];
        //$response['siteExists'] = true;
    }
    else {
        throw new Exception("Site not found in database.");
    }

    // Create a Dictionary to store all Sections we create into one place
    $dictionary = new Dictionary();

    // Spell check each Keyword within the Phrase
    foreach ($phrase->get_all_keywords() as $keyword) {
        $section_char = $dictionary->get_section_from_char($keyword->get_text()[0]);
        if (!$dictionary->has_section($section_char)) {
            // Add a new section to the dictionary
            $section = new Section();
            $section->create_section($pdo, $site_id, $section_char);
            $dictionary->add_section($section);
        }
        spell_check_keyword($keyword, $dictionary);
    }

    $predictions = phrase_predictions($phrase, $dictionary);
    $response['predictions'] = $predictions;

    $suggestions = create_suggestions_from_predictions($phrase, $predictions);
    usort($suggestions, 'sort_suggestions_by_distance');
    $response['suggestions'] = $suggestions;

    // If the suggestions array is not empty, replace the original phrase with the best suggestion (the one with the smallest levenshtein distance)
    if (isset($suggestions[0])) {
        $phrase = $suggestions[0]->get_suggested_phrase();
    }
    $response['phrase'] = $phrase;
    $phrase->set_text($phrase->to_string()); // temporary fix for the Phrase objects to_string() issue.

    // In order to give keywords containing symbols a chance, we will search the paragraphs to see if they contain the keyword as a substring
    $keyword_results_with_symbols = [];
    $keyword_results = [];
    if ($filter_symbols) {
        $keywords_with_symbols = [];
        $keywords_without_symbols = [];
        foreach ($phrase->get_all_keywords() as $keyword) {
            if ($keyword->has_symbol()) {
                $keywords_with_symbols[] = $keyword;
            }
            else {
                $keywords_without_symbols[] = $keyword;
            }
        }
        if (count($keywords_with_symbols) > 0) {
            $keyword_results_with_symbols = fetch_keyword_dupes_from_paragraphs($keywords_with_symbols, $pdo, $site_id);
        }
        if (count($keywords_without_symbols) > 0) {
            $keyword_results = fetch_keyword_dupes($keywords_without_symbols, $pdo, $site_id);
        }
        $keyword_results = array_merge($keyword_results, $keyword_results_with_symbols);
    }
    else {
        $keyword_results = fetch_keyword_dupes($phrase->get_all_keywords(), $pdo, $site_id);
    }
    $response['keyword_results'] = $keyword_results;

    $phrase_results = fetch_phrase_dupes($phrase, $pdo, $site_id);
    $response['phrase_results'] = $phrase_results;

    // Create all results
    $search_results = [];
    foreach ($keyword_results as $result) {
        $search_results[$result['page_id']] = new Result($result['page_id']);
    }

    $score_keeper = rank_results($keyword_results, $phrase_results);
    //$response['score_keeper'] = $score_keeper;

    foreach ($phrase_results as $matched_paragraph) {
        $snippet = generate_snippet($phrase, $matched_paragraph);
        $search_results[$matched_paragraph['page_id']]->add_snippet($snippet, true);
    }

    $page_ids = array_keys($score_keeper->get_all_scores());
    if (!empty($page_ids)) {
        $paths_and_metadata = fetch_all_paths_and_metadata($page_ids, $pdo);

        // Create a Result object for each search result found
        foreach ($paths_and_metadata as $page) {
            // Ensure that this result contains a snippet.
            $snippets = $search_results[$page['page_id']]->get_all_snippets();
            if (empty($snippets) || $snippet === NULL) {
                // If no snippet was made already, just use the page description as the snippet.
                $search_results[$page['page_id']]->add_snippet($page['description'], false);
            }
            $urlNoPath = format_url($url, false);
            $search_results[$page['page_id']]->set_url($urlNoPath . $page['path']);
            $search_results[$page['page_id']]->set_title($page['title']);
            $search_results[$page['page_id']]->set_relevance($score_keeper->get_score($page['page_id']));
        }
    }
    // Sort the pages by their relevance score
    usort($search_results, 'resultSort');

    //$response['relevance_scores'] = $score_keeper->get_all_scores();
    $response['totalResults'] = count($search_results);
    $response['totalPages'] = ceil(count($search_results) / 10);
    $result_pages = array_chunk($search_results, 10);
    if (isset($result_pages[$page_to_return])) {
        $response['results'] = $result_pages[$page_to_return];
    }
} 
catch (Exception $e) {
    // One of our database queries have failed.
    // Print out the error message.
    $response['dbError'] = $e->getMessage();
    $save_search = false;
}

// Store the search that was made by the user
if ($save_search && $page_to_return === 0) { // Only store searches that land on the first page.
    try {
        $pdo->beginTransaction();
        $sql = 'INSERT INTO searches (site_id, search_phrase) VALUES (?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(1, $site_id, PDO::PARAM_INT);
        $statement->bindValue(2, $phrase->to_string(), PDO::PARAM_STR);
        $statement->execute();
        $pdo->commit();
    }
    catch (Exception $e) {
        // One of our database queries have failed.
        // Print out the error message.
        //echo $e->getMessage();
        $response['dbError'] = $e->getMessage();
        // Rollback the transaction.
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// Monitor program performance using this timer
$end = round(microtime(true) * 1000);
$response['timeTaken'] = $end - $begin;

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

// Input: Two Result objects
// Output: Integer signifying whether A is less than, equal to, or greater than B
// Compare Result A with Result B
function resultSort($resA, $resB) {
    $a = $resA->get_relevance();
    $b = $resB->get_relevance();

    if ($a === $b) {
        return 0;
    }
    return ($a > $b) ? -1 : 1;
}

// Input: Two String objects
// Output: -1, 0, 1
// Compares two keywords lexicographically.
function nat_cmp(string $a, string $b) {
    return strnatcasecmp($a, $b);
}

// Input: Two Suggestion objects
// Output: -1, 0, 1
// Compares two Suggestions by their total distance.
function sort_suggestions_by_distance(Suggestion $a, Suggestion $b) {
    $a_dist = $a->get_total_distance();
    $b_dist = $b->get_total_distance();
    if ($a_dist < $b_dist) {
        return -1;
    }
    else if ($a_dist > $b_dist) {
        return 1;
    }
    else {
        return 0;
    }
}

// Input: Two Keyword objects
// Output: -1, 0, 1
// Compare two keywords based on levenshtein distance.
function keyword_distance_cmp($a, $b) {
    $a_dist = $a->get_suggestion_distance();
    $b_dist = $b->get_suggestion_distance();

    if ($a_dist === $b_dist) {
        return 0;
    }
    return ($a_dist < $b_dist) ? -1 : 1;
}

// Input: URL string.
//        (optional) Boolean that determines whether to end the url with a '/'
// Output: URL with or without a path.
// Ensures a given URL has (or doesnt have) a path.
function format_url(string $raw_url, bool $include_path = true) {
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

function spell_check_keyword(Keyword $keyword, Dictionary $dictionary) {
    // Ensure the keyword is actually a keyword and is not a number.
    $first_char = $keyword->get_text()[0];
    $section_char = $dictionary->get_section_from_char($first_char);
    if ($section_char === false || $section_char === "digit") {
        return;
    }

    $section = null;
    if ($dictionary->has_section($section_char)) {
        $section = $dictionary->get_section($section_char);
    }
    else {
        throw new Exception("This Dictionary does not have a Section '" . $section_char ."'.");
    }

    $is_english = $section->search($keyword->get_text());
    $is_short = strlen($keyword->get_text()) <= 2;
    if (!$is_english && !$is_short) {
        $keyword->is_misspelled(true);
    }
}

function spell_check_phrase(Phrase $phrase, Dictionary $dictionary) {
    $keywords = $phrase->get_all_keywords();
    // Fill the keywords array with all possible keywords
    try {
        foreach ($keywords as $index => $keyword) {
            spell_check_keyword($keyword, $dictionary);
        }
    }
    catch (Exception $e) {
        echo $e->getMessage();
    }
}

function keyword_predictions(Keyword $keyword, Dictionary $dictionary) {
    // Verify that the Dictionary contains the necessary Section. 
    $first_letter = $keyword->get_text()[0];
    $section = null;
    $section_char = $dictionary->get_section_from_char($first_letter);
    if ($dictionary->has_section($section_char)) {
        $section = $dictionary->get_section($section_char);
    }
    else {
        throw new Exception("This Dictionary does not have a Section '" . $section_char ."'.");
    }

    // Add predictions to the array.
    return $section->similar_to($keyword);
}

function phrase_predictions(Phrase $phrase, Dictionary $dictionary) {
    $keywords = $phrase->get_all_keywords();
    $predictions = [];
    foreach ($keywords as $index => $keyword) {
        if ($keyword->is_misspelled()) {
            // Add predictions to the array.
            $predictions = array_merge($predictions, keyword_predictions($keyword, $dictionary));
        }
    }
    return $predictions;
}

function create_suggestions_from_history(Phrase $phrase, PDO $pdo, int $site_id, int $limit = 10) {
    $suggestions = [];
    // Find all previous searches that contain the currently typed phrase
    $sql = "SELECT search_phrase,COUNT(search_phrase) AS times_searched FROM searches WHERE site_id = ? AND INSTR(search_phrase, ?)>0 AND INSTR(search_phrase, ?)<=" . strlen($phrase) . " GROUP BY search_phrase ORDER BY times_searched DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase->to_string(), $phrase->to_string()]);
    $suggestions = $statement->fetchAll();
    $suggestions = array_slice($suggestions, 0, $limit); // Ensure that we have at most $limit suggestions
    
    foreach ($suggestions as $i => $suggestion) {
        // String to Phrase conversion
        $terms = explode(' ', $suggestion);
        $keywords = [];
        foreach ($terms as $j => $term) {
            $keywords[] = new Keyword($term, $j);
        }
        $new_phrase = new Phrase($keywords);
        $suggestions[$i] = new Suggestion($phrase, $new_phrase);
    }
    return $suggestions;
}

// This takes every Prediction and outputs all possible combinations that are as long as the prediction with the highest get_index() value.
function create_suggestions_from_predictions(Phrase $phrase, array $predictions) {
    $phrases = []; // Holds both partial and full suggestions (held as Keyword objects, not Prediction objects)
    foreach ($phrase->get_all_keywords() as $index => $keyword) {
        // Identify the keywords which belong in the suggestion's current $index.
        $round = []; // Holds prediction strings for this round.
        if ($keyword->is_misspelled()) {
            foreach ($predictions as $prediction) {
                $hasMatchingIndex = $prediction->get_index() === $index;
                $hasMorePossibilities = $prediction->get_index() <= $index;
                if ($hasMatchingIndex && $hasMorePossibilities) {
                    $round[] = $prediction->get_prediction(); // Note that get_prediction() returns a Keyword object.
                }
            }
        }
        else {
            $round[] = $keyword;
        }

        // For every one Phrase within $phrases, copy count($round) Phrases to a new array.
        if (count($phrases) <= 0) {
            foreach($round as $prediction) {
                $phrases[] = new Phrase([$prediction]);
            }
        }
        else {
            // There's at least one Phrase in the $phrases array. 
            $new_phrases = [];
            foreach ($phrases as $partial_phrase) {
                $fragments = []; // Create new array
                foreach ($round as $keyword) {
                    $new_phrase = new Phrase($partial_phrase->get_all_keywords()); // Create new Phrase for each prediction
                    $new_phrase->set_keyword($keyword, $keyword->get_index());
                    $fragments[] = $new_phrase;
                }
                $new_phrases = array_merge($new_phrases, $fragments);
            }
            $phrases = array_merge($phrases, $new_phrases);
        }
    }

    // Once we have every Phrase built within the $phrases array,
    // remove each Phrase which is shorter than the input $phrase,
    // create Suggestions out of valid Phrases, and
    // remove each Suggestion which has a distance of 0.
    $suggestions = [];
    foreach ($phrases as $possible_suggestion) {
        $possible_suggestion->set_text($possible_suggestion->to_string()); // temporary fix for the Phrase objects to_string() issue.
        $has_same_length = $possible_suggestion->length() === $phrase->length();
        $has_same_distance = levenshtein($possible_suggestion->to_string(), $phrase->to_string()) === 0;
        if ($has_same_length && !$has_same_distance) {
            $suggestions[] = new Suggestion($phrase, $possible_suggestion);
        }
    }
    return $suggestions;
}

// Intended for when searching through the paragraphs is the only option. (basically, a forced search is performed)
function fetch_keyword_dupes_from_paragraphs(array $keywords, PDO $pdo, int $site_id) {
    // Build the SQL query
    $sql = 'SELECT * FROM (';
    foreach ($keywords as $keyword) {
        $sql .= 'SELECT `site_id`, `page_id`, ? AS keyword, ROUND ((LENGTH(paragraph) - LENGTH(REPLACE(paragraph, ?, ""))) / LENGTH(?)) AS dupe_total FROM paragraphs WHERE site_id = ? UNION ALL';
    }
    $sql = substr($sql, 0, -9); // Remove the extra UNION ALL from the end.
    $sql .= ') AS data WHERE dupe_total > 0 ORDER BY dupe_total DESC;';
    // Obtain results based on the whole phrase
    //$sql = 'SELECT * FROM (SELECT `site_id`, `page_id`, `?` AS keyword, ROUND ((LENGTH(paragraph) - LENGTH(REPLACE(paragraph, `?`, ""))) / LENGTH(`?`)) AS dupe_total FROM paragraphs WHERE site_id = ?) AS data WHERE dupe_total > 0 ORDER BY dupe_total DESC;';
    $statement = $pdo->prepare($sql);
    for ($i = 0, $j = 1, $keyword_count = count($keywords); $i < $keyword_count; $i++) {
        $statement->bindValue($j, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+1, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+2, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+3, $site_id, PDO::PARAM_INT);
        $j += 4;
    }
    $statement->execute();
    return $statement->fetchAll();
}

function fetch_keyword_dupes(array $keywords, PDO $pdo, int $site_id) {
    $keyword_strings = []; // Used to hold Prediction and Keyword text

    // Find how many keywords start with each letter.
    $totals = []; // Tracks the total number of keywords with the same first letters.
    foreach ($keywords as $keyword) {
        // Verify that this entry within the array is an object.
        if (gettype($keyword) !== "object") {
            trigger_error("Entry within array is a " . gettype($keyword) . ". Must be either a Keyword or Prediction object. Entry has been skipped.", E_USER_WARNING);
            continue;
        }

        // Next, verify that this object is either a Keyword or a Prediction.
        $first_letter = "";
        if (get_class($keyword) === "Keyword") {
            $keyword_strings[] = $keyword->get_text();
            $first_letter = $keyword->get_text()[0];
        }
        else if (get_class($keyword) === "Prediction") {
            $keyword_strings[] = $keyword->get_prediction()->get_text();
            $first_letter = $keyword->get_prediction()->get_text()[0];
        }
        else {
            trigger_error("Entry within array is of the class " . get_class($keyword) . ". Must be either a Keyword or Prediction object. Entry has been skipped.", E_USER_WARNING);
            continue;
        }

        // Increment the total.
        if (isset($totals[$first_letter])) {
            $totals[$first_letter]++;
        }
        else {
            $totals[$first_letter] = 1;
        }
    }

    // Sort the totals and the keyword_strings to ensure a predictable order.
    ksort($totals);
    natcasesort($keyword_strings);
    $keyword_strings = array_values($keyword_strings); // Re-index the keys so that the entires of $keyword_strings array are ordered as expected.

    // If totals is empty, then we have nothing to search!
    if (empty($totals)) {
        return [];
    }

    // Generate the SQL string
    $sql = '';
    foreach ($totals as $first_letter => $total) {
        $pdo_str = create_pdo_placeholder_str($total, 1);
        $number_regex = "/[0-9]/";
        $is_number = preg_match($number_regex, $first_letter);
        if ($is_number) {
            $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_num WHERE site_id = ? AND keyword IN ' . $pdo_str . ' union ALL ';
        }
        else {
            $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_' . $first_letter . ' WHERE site_id = ? AND keyword IN ' . $pdo_str . ' union ALL ';
        }
    }

    // Replace 'union ALL' with an ORDER BY clause
    $sql = substr($sql, 0, -10);
    $sql .= 'ORDER BY dupe_total DESC'; 

    // Prepare and return the PDO statement
    $statement = $pdo->prepare($sql);
    $sum = 1; // Used for binding values to the correct indices.
    $index = 0; // Used for tracking which keyword we are on. 
    foreach ($totals as $total) {
        $statement->bindValue($sum, $site_id, PDO::PARAM_INT);
        $sum++;
        for ($i = $index; $i < $total + $index; $i++) {
            $statement->bindValue($sum, $keyword_strings[$i], PDO::PARAM_STR);
            $sum++;
        }
        //foreach ($keyword_strings as $keyword_string) {
        //    $statement->bindValue($sum, $keyword_string, PDO::PARAM_STR);
        //    $sum++;
        //}
        $index += $total;
    }
    $statement->execute();
    return $statement->fetchAll();
}

function fetch_phrase_dupes(Phrase $phrase, PDO $pdo, int $site_id) {
    // If there are not enough keywords, then return a blank array.
    if ($phrase->length() < 2) {
        return [];
    }

    // Obtain results based on the whole phrase
    $sql = 'SELECT header_id, page_id, paragraph FROM paragraphs WHERE site_id = ? AND INSTR(paragraph, ?)';
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase->to_string()]);
    return $statement->fetchAll();
}

function get_relevance(int $dupe_total, int $max) {
    return ceil(($dupe_total / $max) * 100);
}

// Relies on the keyword_results being ordered by the dupe_total in descending order.
function rank_results($keyword_results, $phrase_results) {
    // Obtain all relevance scores and populate array of Results
    //$search_results = []; // Contains all Results objects.
    $maxes = []; // Holds each keywords highest dupe totals.
    $score_keeper = new ScoreKeeper();

    if ($keyword_results !== NULL) {
        // Add to score based on individual keywords
        foreach ($keyword_results as $result) {
            //$page_id = $result['page_id'];
            //$search_results[$page_id] = new Result($page_id);
            if (!isset($maxes[$result['keyword']])) {
                $maxes[$result['keyword']] = $result['dupe_total'];
            }
            $score = get_relevance($result['dupe_total'], $maxes[$result['keyword']]);
            $score_keeper->add_to_score($score, $result['page_id']);
            /*foreach ($phrase->get_all_keywords() as $original_keyword) {
                $keywords_match = $result['keyword'] === $original_keyword->get_text();
                $has_max = $original_keyword->get_max() !== null;
                // Storing this max will come in handy when calculating relevance scores.
                if ($keywords_match) {
                    if (!$has_max) {
                        // Store max dupe_total for this keyword
                        $original_keyword->set_max($result['dupe_total']);
                    }
                    $search_results[$page_id] = new Result($page_id);
                    $score = $original_keyword->relevance($result['dupe_total']);
                    $score_keeper->add_to_score($score, $page_id);
                    break;
                }
            }*/
        }
    }
    
    if ($phrase_results !== NULL) {
        // Add to score based on the whole phrase.
        foreach ($phrase_results as $result) {
            // Case-insensitive search for the needle (phrase) in the haystack (content)
            //$phraseMatchIndex = stripos($result['paragraph'], $phrase);
            //if ($phraseMatchIndex !== false) {
                $inflated_score = count($maxes) * 100;
                $score_keeper->add_to_score($inflated_score, $result['page_id']); //[$result['page_id']] += count($keywords) * 100;
            //}
        }
    }
    return $score_keeper;
}

function generate_snippet($phrase, $matched_paragraph) {
    // $matched_paragraph is a paragraph that contains the phrase as a substring.
    // Generate snippets for any results whose content contains the search phrase.

    // Case-insensitive search for the needle (phrase) in the haystack (content)
    $phraseMatchIndex = stripos($matched_paragraph['paragraph'], $phrase->to_string());

    $paragraph_length = strlen($matched_paragraph['paragraph']);
    $charsFromPhrase = 140; // Amount of characters around the phrase to capture for the snippet.
    $clipsAtStart = $phraseMatchIndex < $charsFromPhrase; // Check if we can get 140 characters before the phrase without going below zero.
    $clipsAtEnd = $phraseMatchIndex + $charsFromPhrase > $paragraph_length; // Check if we can get 140 characters after the phrase without going past the snippet length.
    $snippetStart = $phraseMatchIndex - $charsFromPhrase; // Starting index of the snippet
    $distance_to_end = $paragraph_length - $snippetStart;
    $idealLength = $charsFromPhrase * 2; // The ideal length of the snippet.
    if ($clipsAtStart) {
        $snippetStart = 0;
    }
    if ($clipsAtEnd) {
        $idealLength = $distance_to_end;
    }
    
    // Ensures whole word is captured on the beginning edge.
    $is_space_char = ord($matched_paragraph['paragraph'][$snippetStart]) === 32;
    while ($snippetStart > 0) {
        if (!$is_space_char) {
            $snippetStart--;
        }
        else {
            $snippetStart++; // This removes the space from the start of the snippet.
            break;
        }
        $is_space_char = ord($matched_paragraph['paragraph'][$snippetStart]) === 32;
    }
    $snippet = substr($matched_paragraph['paragraph'], $snippetStart, $distance_to_end); // Cut the beginning of the paragraph where the snippet will start.
    if (strlen($snippet) > $idealLength) {
        $snippet = wordwrap($snippet, $idealLength);
        $snippet = substr($snippet, 0, strpos($snippet, "\n"));
    }
    /*
    // Ensures whole word is captured on the ending edge.
    $snippetEnd = $snippetStart + $idealLength;
    $is_space_char = ord($matched_paragraph['paragraph'][$snippetEnd]) === 32;
    while (($snippetEnd < ($paragraph_length - 1)) && !$is_space_char) {
        $idealLength++;
        $snippetEnd++;
        $is_space_char = ord($matched_paragraph['paragraph'][$snippetEnd]) === 32;
    }*/
    //$snippet = substr($matched_paragraph['paragraph'], $snippetStart, $idealLength); // Get around 140 characters before and after the phrase.

    // Remove line breaks from snippet.
    $br_regex = "/<br>/";
    while (preg_match_all($br_regex, $snippet) > 0) {
        // Find the index of the line break
        $match = [];
        preg_match($br_regex, $snippet, $match, PREG_OFFSET_CAPTURE);
        $break_index = $match[0][1];

        // Check whether the line break is to the left or right of the phrase. This info is useful for substringing the snippet properly.
        $phraseIndex = stripos($snippet, $phrase->to_string());
        if ($break_index < $phraseIndex) {
            // <br> is on the left of the phrase. __ signifies the ideal cutoff in the example below.
            // sample text.<br>__New line of text containing phrase...
            $break_index = $break_index + 4;
            $snippet = substr($snippet, $break_index, strlen($snippet) - $break_index);
        }
        else {
            // <br> is on the right of the phrase. __ signifies the ideal cutoff in the example below.
            // sample text containing phrase.__<br>New line of text...
            $snippet = substr($snippet, 0, $break_index);
        }
    }

    // Check if first word is capitalized. If not, add ellipses.
    $capitalized_regex = "/[A-Z]/";
    $is_capitalized = preg_match($capitalized_regex, $snippet[0]);
    if (!$is_capitalized) {
        $snippet = "... " . $snippet;
    }

    // Check if the last word ends with punctuation. If not, add ellipses.
    $punctuation_regex = "/[.!?]/";
    $is_stopped = preg_match($punctuation_regex, $snippet[strlen($snippet) - 1]);
    if (!$is_stopped) {
        $snippet = $snippet . "...";
    }

    //$search_results[$matched_paragraph['page_id']]->add_snippet($snippet, true);
    return $snippet;
}

function fetch_all_paths_and_metadata(array $page_ids, PDO $pdo) {
    // To comunicate with the database as few times as possible, 
    // this SQL query gets filled with all of the page_id's that we need info for.
    $pdo_str = create_pdo_placeholder_str(count($page_ids), 1);
    $sql = 'SELECT page_id, path, title, description FROM pages WHERE page_id IN ' . $pdo_str;
    $statement = $pdo->prepare($sql);
    for ($i = 0; $i < count($page_ids); $i++) {
        $statement->bindValue($i+1, $page_ids[$i], PDO::PARAM_INT);
    }
    $statement->execute();
    return $statement->fetchAll();
}

function str_to_phrase(string $str) {
    $terms = explode(' ', $str);
    $keywords = [];
    foreach ($terms as $index => $term) {
        if ($term !== "") {
            $symbol_regex = "/[^A-Za-z0-9]/";
            $has_symbol = preg_match($symbol_regex, $term[0]) === 1;
            $keywords[$index] = new Keyword($term, $index);
            $keywords[$index]->has_symbol($has_symbol);
        }
    }
    return new Phrase($keywords);
}