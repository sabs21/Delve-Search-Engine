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

class Dictionary {
    public $dict;

    public function __construct($path) {
        $json = file_get_contents($path);
        $this->dict = json_decode($json, TRUE);
    }

    protected function get_dict() {
        return $this->dict;
    }

    public function word_at($index) {
        return $this->dict[$index]['word'];
    }

    public function metaphone_at($index) {
        return $this->dict[$index]['metaphone'];
    }

    public function length() {
        return count($this->dict);
    }

    public function indices_to_words($arr) {
        $result = [];

        for ($i = 0; $i < count($arr); $i++) {
            $result[$i] = $this->dict[$arr[$i]]['word'];
        }

        return $result;
    }

    public function indices_to_metaphones($arr) {
        $result = [];

        for ($i = 0; $i < count($arr); $i++) {
            $result[$i] = $this->dict[$arr[$i]]['metaphone'];
        }

        return $result;
    }
}

// WordDictionary implies a dictionary which is sorted alphabetically by word
class WordDictionary extends Dictionary {
    public function __construct($path) {
        parent::__construct($path);
    }

    // Input: l is low index
    //        h is high index
    //        key is the word we're searching for
    // Output: Index of the located word in the given array (arr)
    // Perform a binary search for a given term based on a word.
    public function search($key) {
        //$arr = parent::get_dict();
        $l = 0;
        $h = parent::length();

        while ($h >= $l) {
            $mid = ceil($l + ($h - $l) / 2); 
    
            // If the element is present at the middle itself 
            if (parent::word_at($mid) === $key) {
                return floor($mid); 
            }
    
            // If element is smaller than mid, then 
            // it can only be present in left subarray 
            if (parent::word_at($mid) > $key) {
                $h = $mid - 1;
            }
            else {
                // Else the element can only be present in right subarray 
                $l = $mid + 1;
            }
        }
    
        // We reach here when element is not present in array 
        return -1;
    }

    // Input: anchor is the index of a word which we want to find more of around it.
    // Output: Array of indices of words with a short levenshtein distance to the word at the anchor.
    // For the metaphone sorted dictionaries
    // Returns an array of indices of words which contain the same metaphone as the word at the given index
    public function word_walk($anchor) {
        $results = [ $anchor ];
        $key = parent::word_at($anchor);
        $max_distance = ceil(strlen($key) / 2);
        $limit = 50; // In case a lot of matches are found, limit the walk to x amount of matches.
        $buffer = 10; // Bypass x amount of words with long postfixes in an attempt to find more potential suggestions in "harder to reach" places.
        
        // Check higher indices for more matches.
        $has_possible_suggestions = true;
        $i = $anchor + 1;
        $k = 0; // Limit counter.
        $outlier_buffer = $buffer; 
        while ($has_possible_suggestions && $k < $limit) {
            $l_distance = levenshtein($key, parent::word_at($i));
            if ($l_distance < $max_distance) {
                $results[] = $i;
            }
            else {
                $outlier_buffer--;
            }

            if ($outlier_buffer <= 0) {
                $has_possible_suggestions = false;
            }
            $i++;
            $k++;
        }

        // Check lower indices for more matches.
        $has_possible_suggestions = true;
        $j = $anchor - 1;
        $k = 0; // Limit counter.
        $outlier_buffer = $buffer;
        while ($has_possible_suggestions && $k < $limit) {
            $l_distance = levenshtein($key, parent::word_at($j));
            if ($l_distance < $max_distance) {
                $results[] = $j;
            }
            else {
                $outlier_buffer--;
            }

            if ($outlier_buffer <= 0) {
                $has_possible_suggestions = false;
            }
            $j--;
            $k++;
        }
    
        return $results;
    }
}

// MetaphoneDictionary implies a dictionary which is sorted alphabetically by metaphone
class MetaphoneDictionary extends Dictionary {
    public function __construct($path) {
        parent::__construct($path);
    }

    // Input: l is low index
    //        h is high index
    //        key is the word we're searching for
    // Output: Index of the located word in the given array (arr)
    // Perform a binary search for a given term based on a metaphone.
    public function search($key) {
        //$arr = parent::get_dict();
        $l = 0;
        $h = parent::length();

        while ($h >= $l) {
            $mid = ceil($l + ($h - $l) / 2); 
    
            // If the element is present at the middle itself 
            if (parent::metaphone_at($mid) === $key) {
                return floor($mid); 
            }
    
            // If element is smaller than mid, then 
            // it can only be present in left subarray 
            if (parent::metaphone_at($mid) > $key) {
                $h = $mid - 1;
            }
            else {
                // Else the element can only be present in right subarray 
                $l = $mid + 1;
            }
        }
    
        // We reach here when element is not present in array 
        return -1;
    }

}

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
    protected $original; // Original term that this keyword references.
    //protected $pages_found;
    public $keyword;
    public $term_index; // Index of the term which this keyword references.
    protected $is_suggestion;
    public $suggestion_distance; // Levenshtein distance between the original term and the suggested term.
    protected $max; // Maximum dupe_totals of this keyword in the database.

    public function __construct($original, $term_index, $suggested = NULL) {
        $this->original = $original;
        $this->term_index = $term_index;
        $this->max = NULL;
        //$this->pages_found = [];
        if (isset($suggested)) {
            $this->keyword = $suggested;
            $this->is_suggestion = true;
            $this->suggestion_distance = levenshtein($original, $suggested);
        }
        else {
            $this->keyword = $original;
            $this->is_suggestion = false;
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

    public function is_suggestion() {
        return $this->is_suggestion;
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
    public $snippet;
    //public $dupeTotals; // An array which holds every dupe_total for this given page
    public $relevance;

    public function __construct($page_id, $url = NULL, $title = NULL, $snippet = NULL, $relevance = 0) {
        $this->page_id = $page_id;
        $this->url = $url;
        $this->title = $title;
        $this->snippet = $snippet;
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

    public function get_snippet() {
        return $this->snippet;
    }
    public function set_snippet($str) {
        $this->snippet = $str;
    }

    public function get_relevance() {
        return $this->relevance;
    }
    public function set_relevance($value) {
        $this->relevance = $value;
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

// Get data from the POST sent from the fetch API
$raw = trim(file_get_contents('php://input'));
$url = json_decode($raw)->url;
$urlNoPath = $url;
$phrase = trim(json_decode($raw)->phrase);
$page_to_return = json_decode($raw)->page - 1; // This value will be used as an array index, so we subtract 1.

// Remove unnecessary characters and seperate phrase into seperate terms
$phrase = sanitize($phrase, ['symbols' => true, 'lower' => false, 'upper' => false]);
$terms = explode(' ', $phrase);

// Format the url which was recieved so that it does not end in '/'
if ($url[strlen($url) - 1] === '/') {
    $urlNoPath = substr($url, 0, strlen($url) - 1);
    //$url .= '/';
}
else {
    $url .= '/'; 
}

// Import English dictionary data to check and correct mis-spellings
$word_dict = new WordDictionary("./wordSorted.json");
// Import metaphone dictionary to find potential mis-spelling corrections
$meta_dict = new MetaphoneDictionary("./metaphoneSorted.json");

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'time_taken' => NULL,
    'keywords' => NULL,
    'site_exists' => false,
    'searchPhrase' => $phrase,
    'searchTerms' => NULL,
    'results' => NULL,
    'totalResults' => NULL,
    'totalPages' => NULL,
    'page' => $page_to_return + 1,
    'relevance_scores' => NULL,
    'matched' => NULL
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
    $pdo->beginTransaction();
    $sql = 'SELECT site_id FROM sites WHERE url = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$url]);
    $sql_res = $statement->fetch(); // Returns an array of *indexed and associative results. Indexed is preferred.

    // Check existence of site in database
    $site_id = NULL;
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
    $i = 0;
    foreach ($terms as $term) {
        // Loop to check if each term is english
        // Check if the word is english to check and see if we need to generate suggestions.
        $word_match = $word_dict->search($term);
        $isEnglish = $word_match !== -1;

        // Then build up the keywords array to generate a large sql query
        if (!$isEnglish) {
            // Formulate the suggestions array, 
            // Then put suggestions into keywords array.

            // Find a ballpark estimate of where the term should be using metaphones.
            $meta_match = $meta_dict->search(metaphone($term));
            $meta_word = $meta_dict->word_at($meta_match);

            // Using the ballpark estimate, walk around to find any potential matches
            $match = $word_dict->search($meta_word);
            $suggestion_indices = $word_dict->word_walk($match);
            $suggestions = $word_dict->indices_to_words($suggestion_indices);

            foreach ($suggestions as $suggestion) {
                $keywords[] = new Keyword($term, $i, $suggestion);
            }
        } 
        else {
            $keywords[] = new Keyword($term, $i);
        }
        $i++;
    }

    usort($keywords, 'nat_keyword_cmp');

    // Display all keywords to be checked in the database.
    // DEBUGGING PURPOSES
    $all_keywords = [];
    foreach ($keywords as $keyword) {
        $all_keywords[] = $keyword->get_keyword();
    }
    $response['keywords'] = $keywords;

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
            $groups[] = ['first_letter' => $first_letter, 'total' => $total];
            $first_letter = $keyword[0];
            $total = 1;
        }
    }
    $groups[] = ['first_letter' => $first_letter, 'total' => $total]; // Store the last group.

    // Generate the sql query
    $sql = '';
    foreach ($groups as $group) {
        $pdo_str = create_pdo_placeholder_str($group['total'], 1);
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

    // Now we can find out which Keywords are useful and which are not.

    usort($keywords, 'keyword_distance_cmp');

    // Determine which keywords are useful.
    foreach ($keywords as $key => $keyword) {
        if (!$keyword->is_suggestion()) {
            continue;
        }

        $is_good_suggestion = false;
        foreach ($results as $result) {
            // If this keyword is found in the database search results, then it's a good suggestion.
            if ($result['keyword'] === $keyword->get_keyword()) {
                $is_good_suggestion = true;
                break;
            }
        }

        if (!$is_good_suggestion) {
            //$keyword->set_as_suggestion(false);
            unset($keywords[$key]); // Remove the entry from the keywords array.
        }
    }

    // Re-index the keywords array to account for all of the removed entries.
    $keywords = array_values($keywords);
    $response['array_val_keywords'] = $keywords;

    // Replace misspelled terms with best suggestions.
    $terms_replaced = []; // Array of indices of all terms which have been replaced.
    foreach ($terms as $term_index => $term) {
        foreach ($keywords as $keyword) {
            if (!$keyword->is_suggestion()) {
                continue;
            }

            if ($term_index === $keyword->get_term_index()) {
                $terms[$term_index] = $keyword->get_keyword();
                $terms_replaced[] = $term_index; // The idea is to bold all of these terms in the front-end when the dialog box appears "Did you mean..."
            }
        }
    }

    $response['searchTerms'] = $terms;
    
    // Obtain all relevance scores and populate array of Results
    $search_results = []; // Contains all Results objects.
    $score_keeper = new ScoreKeeper();
    foreach ($results as $result) {
        $page_id = $result['page_id'];
        $search_results[$page_id] = new Result($page_id);
        foreach ($keywords as $keyword) {
            // Check whether this keyword is actually a search term.
            // If not, move to the next keyword.
            $term_index = $keyword->get_term_index();
            if (!$terms[$term_index] === $keyword->get_keyword()) {
                continue;
            }

            $keywords_match = $result['keyword'] === $keyword->get_keyword();
            $has_max = $keyword->get_max() !== null;
            // Storing this max will come in handy when calculating relevance scores.
            if ($keywords_match) {
                if (!$has_max) {
                    // Store max dupe_total for this keyword
                    $keyword->set_max($result['dupe_total']);
                }

                $score = $keyword->relevance($result['dupe_total']);
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
    $sql = 'SELECT page_id, content FROM contents WHERE site_id = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id]);
    $results = $statement->fetchAll(); // Returns an array of indexed and associative results.

    // Fill the phraseHits array with the indices of any search phrase matches within the content of each page.
    //$phraseHits = [];
    foreach ($results as $result) {
        // Case-insensitive search for the needle (phrase) in the haystack (content)
        $exactMatchIndex = stripos($result['content'], $phrase);
        if ($exactMatchIndex !== false) {
            $inflated_score = count($keywords) * 100;
            $score_keeper->add_to_score($inflated_score, $result['page_id']); //[$result['page_id']] += count($keywords) * 100;
            //$phraseHits[$page_id][] = $exactMatchIndex; // Note the index of where the match was in order to generate a more useful snippet.
            $search_results[$result['page_id']]->set_snippet('Replace this snippet with a nice snippet cutout around where the matching phrase was found.');
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

        // Check if a snippet was already generated for this result.
        $snippet = $search_results[$page_id]->get_snippet();
        if ($snippet === NULL) {
            $search_results[$page_id]->set_snippet($results[$i]['description']);
        }

        $search_results[$page_id]->set_url($urlNoPath . $results[$i]['path']);
        $search_results[$page_id]->set_title($results[$i]['title']);
        $search_results[$page_id]->set_relevance($score_keeper->get_score($page_id));
        
        /*$search_results[$page_id] = new Result($page_id,
                                       $urlNoPath . $results[$i]['path'], 
                                       $results[$i]['title'], 
                                       $snippet, 
                                       $score_keeper->get_score($page_id));*/
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

// Input: A keyword string.
//        A PDO instance.
// Output: Boolean.
// Determine whether or not a keyword is within the sites content.
/*function findKeyword($keyword, $pdo) {
    $sql = 'SELECT keyword FROM keywords_' . $keyword[0] . ' WHERE keyword = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$keyword]);
    $result = $statement->fetch();

    // If $result contains ANYTHING, we know we've found a match and that the keyword exists.
    if (count($result) > 0) {
        return true;
    }
    else {
        return false;
    }
}*/

//if (isset($keyword[1])) { // If the word is longer than 1 letter, then it can be considered a keyword.
/*
if (!isset($keyword[0]) || $keyword[0] === 'a' || $keyword[0] === 'i') {
    return true;
}
*/