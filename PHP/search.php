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
//ini_set('display_errors', 1);

////////////////////////
// CLASS DEFINITIONS //
//////////////////////

class TermDistance {
    public $term;
    public $distance;

    public function __construct($term, $distance) {
        $this->term = $term;
        $this->distance = $distance;
    }
}

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

class Result {
    public $url;
    public $title;
    public $snippet;
    public $relevence;

    public function __construct($url, $title, $snippet, $relevence) {
        $this->url = $url;
        $this->title = $title;
        $this->snippet = $snippet;
        $this->relevence = $relevence;
    }

    public function get_relevance() {
        return $this->relevence;
    }
}

class RelevanceBin {
    protected $bins;
    protected $maximums; // Highest dupes for each term.
    protected $relevance_arr;
    
    public function __construct() {
        $this->bins = [];
        $this->maximums = [];
        $this->relevance_arr = [];
    }

    public function get_bins() {
        return $this->bins;
    }

    // Each bin holds the relevancy score of a given page.
    // If a bin exists for the given page_id, then add the new value to the existing value. 
    // If a bin does not exist for the given page_id, create one and set its value.
    public function add_bin($page_id, $value) {
        /*if (!isset($this->bins[$page_id])) {
            $this->bins[$page_id] = 0;
        }*/
        $this->bins[$page_id][] = $value;
    }

    // Store the highest dupe count of a given term.
    // These maximums will be used to calculate an average of sorts
    // This is STEP 1 of creating the relevance array.
    public function add_max($max) {
        $this->maximums[] = $max;
    }

    // Divide each bin with maximums to calculate the relevance of each page
    // This is STEP 2 of creating the relevance array.
    public function create_relevance_arr() {
        $page_ids = array_keys($this->bins);

        //$i = 0;
        foreach($page_ids as $page_id) {
            //foreach($this->maximums as $max) {
                //$this->bins[$page_id][]
            //}
            $relevance = 0;
            // The amount of bins each page contains is the same as the amount of search terms and is also the same as the amount of maximum dupe counts.
            for ($i = 0; $i < count($this->bins[$page_id]); $i++) {
                /*if (!isset($this->bins[$page_id]['relevance'])) {
                    $this->bins[$page_id]['relevance'] = 0;
                }*/
                $relevance += ceil(($this->bins[$page_id][$i] / $this->maximums[$i]) * 100);
            }
            $this->relevance_arr[$page_id] = $relevance;
            //$i += 1;
        }

        return $this->relevance_arr;
    }

    public function get_relevance_arr() {
        return $this->relevance_arr;
    }
}

/////////////////////
// INITIALIZATION //
///////////////////

// Get data from the POST sent from the fetch API
$raw = trim(file_get_contents('php://input'));
$url = json_decode($raw)->url;
$urlNoPath = $url;
$phrase = json_decode($raw)->phrase;
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
$path = "./wordSorted.json";
$json = file_get_contents($path);
$wordDict = json_decode($json, TRUE);

// Import metaphone dictionary to find potential mis-spelling corrections
$path = "./metaphoneSorted.json";
$json = file_get_contents($path);
$metaDict = json_decode($json, TRUE);

// Use this array as a basic response object. May need something more in depth in the future.
$response = [
    'time_taken' => NULL,
    'searchPhrase' => $phrase,
    'searchTerms' => $terms,
    'results' => NULL,
    'totalResults' => NULL,
    'totalPages' => NULL,
    'page' => $page_to_return + 1,
    'relevance_arr' => NULL,
    'matched' => NULL,
    'suggestions' => NULL,
    'suggestions_sorted' => NULL
];

// $response['misc'] = substr($url, 0, strlen($url) - 1);

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
/*$response = [
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
];*/

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

///////////////////////////////////////////////
// CHECK N' GUESS THE INTENDED SEARCH TERMS //
/////////////////////////////////////////////

// Search the the list of keywords from the database first and foremost!
// If no luck, then search the dictionary...
// If still no luck, find the word with the same metaphone and the shortest Levenshtein distance.
// If STILL no luck, remove the term from the search terms.

// Use wordSorted to find the word to make sure it's spelled right. 
// If the term is not in the dictionary, it's spelled wrong.
/*$path = "./wordSorted.json";

$json = file_get_contents($path);
$dict = json_decode($json, TRUE);

foreach($terms as $term) {
    $matchIndex = binarySearchWord($dict, 0, count($dict) - 1, $term);
    $response['matched'][] = $dict[$matchIndex]['word'];
    //$response['matched'] = $dict[$matchIndex]->word;
}*/


// For terms that are spelled wrong, use metaphoneSorted to find any possible matches.
// We want to find the word with the same metaphone and the shortest Levenshtein distance.

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
    $sql_res = $statement->fetch(); // Returns an array of *indexed and associative results. Indexed is preferred.
    $site_id = $sql_res['site_id']; // *Accessing indexes don't seem to work from the fetch() 

    //$response['misc'] = $sql_res;
    
    // Create a new array of bins which will hold the relevance score for each page.
    $bins = new RelevanceBin();

    // Obtain results for each term in the search phrase
    foreach ($terms as $term) {
        // Search through keywords for all pages which contain a matching keyword. We use the first letter of the term to select keywords from the correct table.
        $sql = 'SELECT page_id, dupe_count FROM keywords_' . $term[0] . ' WHERE keyword = ? AND site_id = ? ORDER BY page_id DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute([$term, $site_id]);
        $results = $statement->fetchAll(); // Returns an array of indexed and associative results.

        // If at least one result is found, then begin to generate relevance scores for each page with this keyword.
        if (count($results) > 0) {
            $max = 0;

            // Add up the relevance score for each page based on keyword occurances on the page.
            foreach ($results as $result) {
                $bins->add_bin($result['page_id'], $result['dupe_count']);
                if ($result['dupe_count'] > $max) {
                    $max = $result['dupe_count'];
                }
            }

            // Add the max dupe_count for this term to the max array in the RelevanceBin instance.
            // We will use this to calculate relevance for each page later.
            $bins->add_max($max);
        }
        else {
            // If no results are found, this could indicate a mis-spelled word.
            // Binary search the imported English dictionary for any matches.
            $matchIndex = binarySearchWord($wordDict, 0, count($wordDict) - 1, $term);

            if ($matchIndex !== -1) {
                $response['matched'][] = $wordDict[$matchIndex]['word'];
            }
            else {
                // If the binarySearch didn't find the word, then there has been a mis-spelling.
                $suggestions = getAllMetaphones($metaDict, 0, count($metaDict) - 1, metaphone($term));

                if (count($suggestions) > 0) {
                    $response['suggestions'] = $suggestions;

                    $suggestions_sorted = sortSuggestions($suggestions, $term);
                    $response['suggestions_sorted'] = $suggestions_sorted;
                }
                
                // If there are no suggestions for what the word can be, we must ignore the search term and continue on.
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
    //$sql = 'SELECT page_id, content FROM contents WHERE site_id = ?';
    //$statement = $pdo->prepare($sql);
    //$statement->execute([$site_id]);
    //$contents = $statement->fetchAll(); // Returns an array of indexed and associative results.
    

    // Sort the pages by their relevance score
    //$bins = $bins->get_bins();
    $relevance_arr = $bins->create_relevance_arr();
    //arsort($relevance_arr); // Sorted in descending order (most relevant to least relevant).
    //$relevant_pages = $bins;

    // Put all array keys (aka page_id's) into a separate array.
    $page_ids = array_keys($relevance_arr);
    //$response['misc'] = $page_ids;

    // SELECT * FROM contents WHERE page_id IN ('2901', '2911', '2906', '2921') AND site_id = 53

    // To comunicate with the database as few times as possible, 
    // this SQL query gets filled with all of the page_id's that we need info for.
    $pdo_str = create_pdo_placeholder_str(count($page_ids), 1);
    $sql = 'SELECT page_id, path, title, description FROM pages WHERE page_id IN ' . $pdo_str;
    $statement = $pdo->prepare($sql);
    for ($i = 0; $i < count($page_ids); $i++) {
        $statement->bindValue($i+1, $page_ids[$i], PDO::PARAM_INT);
    }
    $statement->execute();
    $results = $statement->fetchAll(); // Returns an array of indexed and associative results. Indexed is preferred.
    $response['misc'] = $results;

    // Create a Result object for each search result found
    $search_results = [];
    for ($i = 0; $i < count($page_ids); $i++) {
        $page_id = $results[$i]['page_id'];
        $search_results[] = new Result($urlNoPath . $results[$i]['path'], 
                                       $results[$i]['title'], 
                                       $results[$i]['description'], 
                                       $relevance_arr[$page_id]);
    }

    usort($search_results, 'resultSort');

    // Grab pages from the database in the order of page relevance.
    //$search_results = [];
    //foreach ($page_ids as $page_id) {
    //    $sql = 'SELECT path, title, description FROM pages WHERE page_id = ' . $page_id;
    //    $statement = $pdo->prepare($sql);
    //    $statement->execute();
    //    $results = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
    //
    //    $search_results[] = new Result($urlNoPath . $results[0], $results[1], $results[2]);
    //}

    $response['relevance_arr'] = $relevance_arr;
    $response['totalResults'] = count($search_results);
    $response['totalPages'] = ceil(count($search_results) / 10);
    $result_pages = array_chunk($search_results, 10);
    $response['results'] = $result_pages[$page_to_return];
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

// Input: arr is the array of words
//        l is low index
//        h is high index
//        key is the word we're searching for
// Output: Index of the located word in the given array (arr)
// For checking spelling and search terms
function binarySearchWord($arr, $l, $h, $key) { 
    while ($h >= $l) {
        $mid = ceil($l + ($h - $l) / 2); 

        // If the element is present at the middle itself 
        if ($arr[$mid]['word'] == $key) {
            return floor($mid); 
        }

        // If element is smaller than mid, then 
        // it can only be present in left subarray 
        if ($arr[$mid]['word'] > $key) {
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

// Input: arr is the array of metaphones
//        l is low index
//        h is high index
//        key is the metaphone we're searching for
// Output: Return an array of results.
// Binary search for the first metaphone, then search around that first match for any more metaphones.
function getAllMetaphones($arr, $l, $h, $key) { 
    $results = []; // All found metaphones
    $index = -1; // The index of the first matched metaphone.

    // Peform a binary search.
    while ($h >= $l) {
        $mid = ceil($l + ($h - $l) / 2); 

        // If the element is present at the middle itself 
        if ($arr[$mid]['metaphone'] == $key) {
            $results[] = $arr[floor($mid)]['word'];
            $index = floor($mid); 
            break;
        }

        // If element is smaller than mid, then 
        // it can only be present in left subarray 
        if ($arr[$mid]['metaphone'] > $key) {
            $h = $mid - 1;
        }
        else {
            // Else the element can only be present in right subarray 
            $l = $mid + 1;
        }
    }

    if ($index !== -1) {
        // Check higher indices for more matches.
        for ($i = $index + 1; $arr[$i]['metaphone'] == $key; $i++) {
            $results[] = $arr[$i]['word'];
        }

        // Check lower indices for more matches.
        for ($j = $index - 1; $arr[$j]['metaphone'] == $key; $j--) {
            $results[] = $arr[$j]['word'];
        }
    }

    return $results;
} 

// Input: Array obtained from getAllMetaphones()
//        Search term
// Output: Array sorted by closest match to furthest match.
function sortSuggestions($suggestions, $key) {
    $results = [];
    foreach ($suggestions as $suggestion) {
        $results[] = ['distance' => levenshtein($suggestion, $key), 'term' => $suggestion];
    }

    usort($results, function ($result1, $result2) {
        return $result1['distance'] <=> $result2['distance'];
    });

    return $results;
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