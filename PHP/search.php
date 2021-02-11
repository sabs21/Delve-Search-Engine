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
error_reporting(E_ALL);
ini_set('display_errors', 1);
//ini_set('display_errors', 0);

////////////////////////
// CLASS DEFINITIONS //
//////////////////////

// Consider replacing relevance array with a ScoreKeeper class.
class ScoreKeeper {
    protected $scores;

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

class Keyword {
    public $original; // Original term that this keyword references.
    //protected $pages_found;
    public $keyword;
    public $term_index; // Index of the term which this keyword references.
    public $has_misspelling; // Flag for whether the original keyword is misspelled.
    public $has_suggestion;
    public $suggestion_distance; // Levenshtein distance between the original term and the suggested term.
    protected $max; // Maximum dupe_totals of this keyword in the database.

    public function __construct($original, $term_index, $suggested = NULL) {
        $this->original = $original;
        $this->term_index = $term_index;
        $this->max = NULL;
        //$this->pages_found = [];
        if (isset($suggested)) {
            $this->keyword = $suggested;
            $this->has_suggestion = true;
            $this->has_misspelling = true;
            $this->suggestion_distance = levenshtein($original, $suggested);
        }
        else {
            $this->keyword = $original;
            $this->has_suggestion = false;
            $this->has_misspelling = false;
            $this->suggestion_distance = 0;
        }
    }

    public function get_term_index() {
        return $this->term_index;
    }

    public function get_suggestion_distance() {
        return $this->suggestion_distance;
    }

    public function get_original() {
        return $this->original;
    }

    public function get_keyword() {
        return $this->keyword;
    }

    public function set_keyword($new_keyword) {
        if ($new_keyword !== $this->original) {
            $this->keyword = $new_keyword;
            $this->has_suggestion = true;
            $this->suggestion_distance = levenshtein($this->original, $new_keyword);
        }
        else {
            $this->keyword = $this->original;
            $this->has_suggestion = false;
            $this->suggestion_distance = 0;
        }
    }

    public function has_suggestion($bool = null) {
        if ($bool !== null) {
            $this->has_suggestion = $bool;
        }
        return $this->has_suggestion;
    }

    // This refers to $original, not $keyword
    public function has_misspelling($bool = null) {
        if ($bool !== null) {
            $this->has_misspelling = $bool;
        }
        return $this->has_misspelling;
    }

    public function get_max() {
        return $this->max;
    }

    public function set_max($new_max) {
        $this->max = $new_max;
    }

    // If the max is set, output a relevance score.
    // If the max is not set, output 0;
    public function relevance($dupe_total) {
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

    public function __construct($page_id, $url = NULL, $title = NULL, $relevance = 0) {
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
    public function set_title($str) {
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
    public function add_snippet($str, $from_page_content) {
        $this->snippets[] = ["text" => $str, "fromPageContent" => $from_page_content];
    }

    public function get_relevance() {
        return $this->relevance;
    }
    public function set_relevance($value) {
        $this->relevance = $value;
    }
}

// This dictionary is created from the website's keywords.
// The purpose of this over a traditional dictionary is better suggestions.
class LocalDictionary {
    public $dict;
    public $section; // Letter which all words begin with.

    public function __construct() {
        //$json = file_get_contents($path);
        $this->dict = null;//json_decode($json, TRUE);
        $this->section = null;
    }

    // All dictionaries must be created before they are retrieved.
    public function create_dictionary($pdo, $site_id, $first_letter) {
        try {
            $sql = 'SELECT DISTINCT keyword FROM keywords_' . $first_letter . ' WHERE site_id = ? ORDER BY keyword ASC;';
            $statement = $pdo->prepare($sql);
            $statement->execute([$site_id]);
            $this->dict = $statement->fetchAll();
            $this->section = $first_letter;
            return true;
        } 
        catch (Exception $e) {
            // Our database queries has failed.
            // Print out the error message.
            //$response['db_error'] = $e->getMessage();
            return false;
        }
    }

    public function get_dictionary() {
        return $this->dict;
    }

    public function word_at($index) {
        return $this->dict[$index]['keyword'];
    }

    public function length() {
        return count($this->dict);
    }

    // Input: key is the word we're searching for
    // Output: Array containing a flag stating if an exact match was found, and the index of the match.
    // Perform a binary search for a given term based on a word.
    public function search($key) {
        //$arr = parent::get_dict();
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
    public function similar_to($word, $term_index) {
        $similar_words = [];
        $max_distance = 2; // Maximum allowed levenshtein distance
        foreach ($this->get_dictionary() as $entry) {
            $distance = levenshtein($word, $entry['keyword']);
            if ($distance <= $max_distance) {
                $similar_words[] = new Keyword($word, $term_index, $entry['keyword']);
            }
        }
        return $similar_words;
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

// Get data from the POST sent from the fetch API
$raw = trim(file_get_contents('php://input'));
$url = json_decode($raw)->url;
$urlNoPath = $url;
$phrase = trim(json_decode($raw)->phrase); // This phrase will get replaced after spellchecking is complete.
$page_to_return = json_decode($raw)->page - 1; // This value will be used as an array index, so we subtract 1.
$site_id = NULL;

// Remove unnecessary characters and seperate phrase into seperate terms
$phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]);
$terms = explode(' ', $phrase);
$original_keywords = [];
foreach ($terms as $index => $term) {
    $original_keywords[$index] = new Keyword($term, $index);
}

// Format the url which was recieved so that it does not end in '/'
if ($url[strlen($url) - 1] === '/') {
    $urlNoPath = substr($url, 0, strlen($url) - 1);
    //$url .= '/';
}
else {
    $url .= '/'; 
}

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'db_error' => NULL,
    'hasMisspelling' => false,
    'keywords' => NULL,
    'matched' => NULL,
    'page' => $page_to_return + 1,
    'pdo_error' => NULL,
    'relevance_scores' => NULL,
    'results' => NULL,
    'site_exists' => false,
    'phrase' => NULL,
    'terms' => NULL,
    'time_taken' => NULL,
    'totalResults' => NULL,
    'totalPages' => NULL,
    'url' => $url,
    'useful_keywords' => NULL,
];

/////////////////////////////////
// PREPARE TO SEARCH DATABASE //
///////////////////////////////

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

/////////////////////////////
// DATABASE COMMUNICATION //
///////////////////////////

try {
    if (!isset($pdo)) {
        throw new Exception("PDO instance is not defined.");
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

    // Contains both keywords and suggestions which will be searched within the database.
    $keywords = [];

    // Fill the keywords array with all possible keywords
    foreach ($original_keywords as $index => $original_keyword) {
        $first_letter = $original_keyword->get_keyword()[0];
        $dict = new LocalDictionary();
        $dict->create_dictionary($pdo, $site_id, $first_letter);
        $is_english = $dict->search($original_keyword->get_keyword());

        if (!$is_english) {
            // Indicate that this phrase contains a misspelling
            $response['hasMisspelling'] = true;
            $original_keyword->has_misspelling(true);

            // Merge these new suggestions with all previous ones.
            $keywords = array_merge($keywords, $dict->similar_to($original_keyword->get_keyword(), $index));
        }
        else {
            $keywords[] = $original_keyword;
        }
    }

    usort($keywords, 'nat_keyword_cmp');
    $response['possible_keywords'] = $keywords;

    // Tally each group of keywords with the same first letter
    // Used to generate a big keyword search query
    $groups = [];
    $total = 0; // total refers to the total keywords with the same first letter
    $first_letter = $keywords[0]->get_keyword()[0];
    foreach ($keywords as $keyword) {
        $keyword = $keyword->get_keyword();
        // Look for any change in first letter since the last iteration.
        // If there has been a change, reset the total counter.
        if ($keyword[0] === $first_letter) {
            $total += 1;
        }
        else {
            $groups[] = ['first_letter' => $first_letter, 'total_keywords' => $total];
            $first_letter = $keyword[0];
            $total = 1;
        }
    }
    $groups[] = ['first_letter' => $first_letter, 'total_keywords' => $total]; // Store the last group.

    // Generate the sql query
    $sql = '';
    foreach ($groups as $group) {
        $pdo_str = create_pdo_placeholder_str($group['total_keywords'], 1);
        $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_' . $group['first_letter'] . ' WHERE keyword IN ' . $pdo_str . ' union ALL ';
    }
    // Remove the extra 'union ALL' from the end of the SQL string and replace it with an ORDER BY clause
    $sql = substr($sql, 0, -10);
    $sql .= 'ORDER BY dupe_total DESC';
    $statement = $pdo->prepare($sql);
    for ($i = 0; $i < count($keywords); $i++) {
        // Since we cannot use PDO to directly execute an array of objects,
        // we use this for loop to solve that issue.
        $statement->bindValue($i+1, $keywords[$i]->get_keyword(), PDO::PARAM_STR);
    }
    $statement->execute();
    $results = $statement->fetchAll();

    $response['mass_query_results'] = $results;

    usort($keywords, 'keyword_distance_cmp');

    // Form a new search phrase to replace misspelled terms with best suggestions.
    $phrase = NULL;
    foreach ($original_keywords as $term_index => $original_keyword) {
        //$keyword_found = false;
        foreach ($keywords as $keyword) {
            // Find the first keyword which matches the given term_index. Replace the old term with the corrected term.
            if ($term_index === $keyword->get_term_index()) {
                // The idea is to bold all replaced terms in the front-end when the dialog box appears "Did you mean..."
                //$terms[$term_index] = $keyword;
                $original_keyword->set_keyword($keyword->get_keyword());
                //$keyword_found = true;
                break;
            }
        }
        //if ($keyword_found) {
        $phrase .= $original_keyword->get_keyword() . ' '; // Re-create the search phrase in order to account for the new terms we're using.
        //}
    }
    $phrase = substr($phrase, 0, -1); // Remove the space at the end.

    $response['phrase'] = $phrase;
    $response['terms'] = $original_keywords;

    // Generate all possible suggestions
    $fragments = []; // Collects keywords as we go through term indices. As we go, we build on what's in this array to eventually form all possible suggestions.
    foreach ($original_keywords as $term_index => $original_keyword) {
        $total_fragments = count($fragments);
        if (!$original_keyword->has_suggestion()) {
            if ($total_fragments > 0) {
                // Apply this term to every suggestion currently within the fragments array
                for ($i = 0; $i < $total_fragments; $i++) {
                    $fragments[] = $fragments[$i] . " " . $original_keyword->get_keyword();
                }
            }
            else {
                // Add the original term input by the user since we did not find a replacement for this term.
                $fragments[] = $original_keyword->get_keyword();
            }
        }
        else {
            $found_suggestion = false;
            foreach ($keywords as $keyword) {
                // Collect all keywords for this current $term_index value.
                if ($keyword->get_term_index() === $term_index) {
                    $found_suggestion = true;
                    if ($total_fragments > 0) {
                        // Apply this term to every suggestion currently within the fragments array
                        for ($i = 0; $i < $total_fragments; $i++) {
                            $fragments[] = $fragments[$i] . " " . $keyword->get_keyword();
                        }
                    }
                    else {
                        $fragments[] = $keyword->get_keyword();
                    }
                }
            }
            if (!$found_suggestion) {
                $fragments[] = $original_keyword->get_keyword();
            }
        }
    }
    // Extract all suggestions which are of the correct length.
    $suggestions = [];
    for ($i = 0; $i < count($fragments); $i++) {
        $total_terms = count(explode(' ', $fragments[$i]));
        $is_search_phrase = strcmp($fragments[$i], $phrase) === 0; // There's no point suggesting the search phrase that the user just used. Hence we filter it out of the suggestions array.
        if ($total_terms >= count($original_keywords) && !$is_search_phrase) {
            $suggestions[] = $fragments[$i];
        }
    }
    $response['suggestions'] = $suggestions;

    // Obtain all relevance scores and populate array of Results
    $search_results = []; // Contains all Results objects.
    $score_keeper = new ScoreKeeper();
    foreach ($results as $result) {
        $page_id = $result['page_id'];
        foreach ($original_keywords as $original_keyword) {
            $keywords_match = $result['keyword'] === $original_keyword->get_keyword();
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
        }
    }

    // Find all contents which contain the search phrase here. This is the algorithm:
    // Store the page_id's and index of the phrase in the content inside an array called phraseHits.
    // Next, iterate through the page_id and first occurance array. 
    // On each iteration... 
    //      Grab 70 characters of text behind and after the search phrase.
    //      Increment the relevence score by the maximum score possible.
    //          What's the max score possible? Multiply the length of the search terms array by 100.
    //          How to increment the relevence score? $bins->add_bin($phraseHits['page_id'], $maxScore);

    // Obtain results based on the whole phrase
    $sql = 'SELECT header_id, page_id, paragraph FROM paragraphs WHERE site_id = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id]);
    $results = $statement->fetchAll(); // Returns an array of indexed and associative results.

    // Fill the phraseHits array with the indices of any search phrase matches within the content of each page.
    //$phraseHits = [];
    foreach ($results as $result) {
        // Case-insensitive search for the needle (phrase) in the haystack (content)
        $phraseMatchIndex = stripos($result['paragraph'], $phrase);
        if ($phraseMatchIndex !== false) {
            $inflated_score = count($original_keywords) * 100;
            $score_keeper->add_to_score($inflated_score, $result['page_id']); //[$result['page_id']] += count($keywords) * 100;

            $paragraph_length = strlen($result['paragraph']);
            $charsFromPhrase = 140; // Amount of characters around the phrase to capture for the snippet.
            $clipsAtStart = $phraseMatchIndex < $charsFromPhrase; // Check if we can get 140 characters before the phrase without going below zero.
            $clipsAtEnd = $phraseMatchIndex + $charsFromPhrase > $paragraph_length; // Check if we can get 140 characters after the phrase without going past the snippet length.
            $snippetStart = $phraseMatchIndex - $charsFromPhrase; // Starting index of the snippet
            $snippetLength = $charsFromPhrase * 2; // Length of the snippet.
            if ($clipsAtStart) {
                $snippetStart = 0;
            }
            if ($clipsAtEnd) {
                $snippetLength = strlen($result['paragraph']) - $snippetStart;
            }
            $snippet = substr($result['paragraph'], $snippetStart, $snippetLength); // Get 140 characters after the phrase.
            //$phraseHits[] = "snippetStart: " . $snippetStart;
            //$phraseHits[] = "snippetLength: " . $snippetLength;
            //$phraseHits[] = "snippet: " . $snippet;

            // Remove line breaks from snippet.
            $brRegex = "/<br>/";
            while (preg_match_all($brRegex, $snippet) > 0) {
                // Find the index of the line break
                $match = [];
                preg_match($brRegex, $snippet, $match, PREG_OFFSET_CAPTURE);
                $matchIndex = $match[0][1];

                // Check whether the line break is to the left or right of the phrase. This info is useful for substringing the snippet properly.
                $phraseIndex = stripos($snippet, $phrase);
                if ($matchIndex < $phraseIndex) {
                    // <br> is on the left of the phrase. __ signifies the ideal cutoff in the example below.
                    // sample text.<br>__New line of text containing phrase...
                    $matchIndex = $matchIndex + 4;
                    $snippet = substr($snippet, $matchIndex, strlen($snippet) - $matchIndex);
                }
                else {
                    // <br> is on the right of the phrase. __ signifies the ideal cutoff in the example below.
                    // sample text containing phrase.__<br>New line of text...
                    $snippet = substr($snippet, 0, $matchIndex);
                }
            }
            $search_results[$result['page_id']]->add_snippet($snippet, true);
        }
    }

    // Put all array keys (aka page_id's) into a separate array.
    $page_ids = array_keys($score_keeper->get_all_scores());

    // To comunicate with the database as few times as possible, 
    // this SQL query gets filled with all of the page_id's that we need info for.
    $pdo_str = create_pdo_placeholder_str(count($page_ids), 1);
    $sql = 'SELECT page_id, path, title, description FROM pages WHERE page_id IN ' . $pdo_str;
    $statement = $pdo->prepare($sql);
    for ($i = 0; $i < count($page_ids); $i++) {
        $statement->bindValue($i+1, $page_ids[$i], PDO::PARAM_INT);
    }
    $statement->execute();
    $results = $statement->fetchAll();

    // Create a Result object for each search result found
    
    for ($i = 0; $i < count($results); $i++) {
        $page_id = $results[$i]['page_id'];

        // Ensure that this result contains a snippet.
        $snippets = $search_results[$page_id]->get_all_snippets();
        if (empty($snippets) || $snippet === NULL) {
            // If no snippet was made already, just use the page description as the snippet.
            $search_results[$page_id]->add_snippet($results[$i]['description'], false);
        }

        $search_results[$page_id]->set_url($urlNoPath . $results[$i]['path']);
        $search_results[$page_id]->set_title($results[$i]['title']);
        $search_results[$page_id]->set_relevance($score_keeper->get_score($page_id));
    }

    // Sort the pages by their relevance score
    usort($search_results, 'resultSort');

    $response['relevance_scores'] = $score_keeper->get_all_scores();
    $response['totalResults'] = count($search_results);
    $response['totalPages'] = ceil(count($search_results) / 10);
    $result_pages = array_chunk($search_results, 10);
    $response['results'] = $result_pages[$page_to_return];
} 
catch (Exception $e) {
    // One of our database queries have failed.
    // Print out the error message.
    $response['db_error'] = $e->getMessage();
}

// Store the search that was made by the user
if ($page_to_return === 0) { // Only store searches that land on the first page.
    try {
        $pdo->beginTransaction();
        $sql = 'INSERT INTO searches (site_id, search_phrase) VALUES (?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(1, $site_id, PDO::PARAM_INT);
        $statement->bindValue(2, $phrase, PDO::PARAM_STR);
        $statement->execute();
        $pdo->commit();
    }
    catch (Exception $e) {
        // One of our database queries have failed.
        // Print out the error message.
        //echo $e->getMessage();
        $response['db_error'] = $e->getMessage();
        // Rollback the transaction.
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

// Input: Two Keyword objects
// Output: -1, 0, 1
// Compares two keywords lexicographically.
function nat_keyword_cmp($a, $b) {
    return strnatcasecmp($a->get_keyword(), $b->get_keyword());
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