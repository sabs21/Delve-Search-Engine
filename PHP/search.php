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

    /*public function word_at($index) {
        //return $this->dict[$index]['word'];
        return parent::word_at($index);
    }*/
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

    // Input: anchor is the index of a metaphone which we want to find more of around it.
    // Output: Array of indices that contain matching metaphones in the dictionary.
    // For the metaphone sorted dictionaries
    // Returns an array of indices of words which contain the same metaphone as the word at the given index
    public function metaphone_walk($anchor) {
        $results = [ $anchor ];
        $key = parent::metaphone_at($anchor);

        // Check higher indices for more matches.
        for ($i = $anchor + 1; parent::metaphone_at($i) == $key; $i++) {
            $results[] = $i; //parent::word_at($i);
        }

        // Check lower indices for more matches.
        for ($j = $anchor - 1; parent::metaphone_at($j) == $key; $j--) {
            $results[] = $j; //parent::word_at($j);
        }
    
        return $results;
    }
}

class Result {
    public $page_id; // Unused for now
    public $url;
    public $title;
    public $snippet;
    //public $dupeTotals; // An array which holds every dupe_total for this given page
    public $relevance;

    public function __construct($page_id, $url, $title = NULL, $snippet = NULL, $relevance = 0) {
        $this->page_id = $page_id;
        $this->url = $url;
        $this->title = $title;
        $this->snippet = $snippet;
        //$this->dupeTotals = [];
        $this->relevance = $relevance;
    }

    public function add_to_relevance($value) {
        $this->relevance += $value;
    }

    public function get_relevance() {
        return $this->relevance;
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
/*$path = "./wordSorted.json";
$json = file_get_contents($path);
$wordDict = json_decode($json, TRUE);

// Import metaphone dictionary to find potential mis-spelling corrections
$path = "./metaphoneSorted.json";
$json = file_get_contents($path);
$metaDict = json_decode($json, TRUE);*/

$word_dict = new WordDictionary("./wordSorted.json");
$meta_dict = new MetaphoneDictionary("./metaphoneSorted.json");

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
    'time_taken' => NULL,
    'site_exists' => false,
    'searchPhrase' => $phrase,
    'searchTerms' => $terms,
    'results' => NULL,
    'totalResults' => NULL,
    'totalPages' => NULL,
    'page' => $page_to_return + 1,
    'relevance_scores' => NULL,
    'matched' => NULL,
    'suggestions' => NULL,
    'suggestions_sorted' => NULL
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

    // Use relevance_scores array to incrementally tally each relevance score for each page.
    $relevance_scores = [];

    // best_suggestions contains the first suggestion found within the site content for each misspelled term. 
    // This is  useful for normalizing any suggestions which replaced the original term.
    //$best_suggestions = [];

    // search_results contains all Results objects.
    $search_results = [];

    // Contains both keywords and suggestions which will be searched within the database.
    $keywords = [];

    // Fill the keywords array with all possible keywords
    foreach ($terms as $term) {
        // Loop to check if each term is english
        // Check if the word is english to check and see if we need to generate suggestions.
        $word_match = $word_dict->search($term);
        $isEnglish = $word_match !== -1;

        // Then build up the keywords array to generate a large sql query
        if (!$isEnglish) {
            // Formulate the suggestions array, 
            // Then put suggestions into keywords array.
            $meta_match = $meta_dict->search(metaphone($term));
            $suggestion_indices = $meta_dict->metaphone_walk($meta_match);
            $suggestions = $meta_dict->indices_to_words($suggestion_indices);

            foreach ($suggestions as $suggestion) {
                $keywords[] = $suggestion;
            }
        } else {
            $keywords[] = $term;
        }
    }

    sort($keywords);

    //$response['keywords_arr'] = $keywords;

    // Find all first letters which are used throughout all the keywords.
    // This array will also be useful for detecting the first occurence of a keyword in the sql query results.
    // Empty the array as you go so that you know you're at the first occurence when the letter still exists within the array.
    $keyword_sets = [];
    $total = 0; // total refers to the total keywords with the same first letter
    $first_letter = $keywords[0][0];
    foreach ($keywords as $keyword) {
        // Look for any change in first letter since the last iteration.
        // If there has been a change, reset the total counter.
        if ($keyword[0] === $first_letter) {
            $total += 1;
            //$unique_first_letters[] = $keyword[0];
        }
        else {
            $keyword_sets[] = ['first_letter' => $first_letter, 'total' => $total];
            $first_letter = $keyword[0];
            $total = 1;
        }
    }

    // Store the last keyword_set.
    $keyword_sets[] = ['first_letter' => $first_letter, 'total' => $total];

    //$response['keyword_sets'] = $keyword_sets;

    // Generate the sql query
    $sql = '';
    // A keyword_set is a group of keywords who share the same first letter.
    foreach ($keyword_sets as $keyword_set) {
        $pdo_str = create_pdo_placeholder_str($keyword_set['total'], 1);
        $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_' . $keyword_set['first_letter'] . ' WHERE keyword IN ' . $pdo_str . ' union ALL ';
    }
    // Remove the extra 'union ALL' from the end of the SQL string and replace it with an ORDER BY clause
    $sql = substr($sql, 0, -10);
    $sql .= 'ORDER BY dupe_total DESC';

    $response['sql_query'] = $sql;

    $statement = $pdo->prepare($sql);
    $statement->execute($keywords);
    $results = $statement->fetchAll();

    $response['mass_query_results'] = $results;


    // $pdo_str = create_pdo_placeholder_str(count($keywords), 1);
    // $sql = 'SELECT page_id, dupe_total FROM keywords_' . $keywords[0][0] . ' WHERE keyword IN ' . $pdo_str . ' AND site_id = ? ORDER BY dupe_total DESC'

    // Obtain results for each term in the search phrase
    foreach ($terms as $term) {
        // Search through keywords for all pages which contain a matching keyword. We use the first letter of the term to select keywords from the correct table.
        $sql = 'SELECT page_id, dupe_total FROM keywords_' . $term[0] . ' WHERE keyword = ? AND site_id = ? ORDER BY dupe_total DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute([$term, $site_id]);
        $results = $statement->fetchAll(); // Returns an array of indexed and associative results.

        // If at least one result is found, then mark this keyword as valid.
        $isValidTerm = false;
        $suggestions = [];
        if (count($results) > 0) {
            $isValidTerm = true;
        }
        else {
            // If no results are found, this could indicate a mis-spelled word.
            // Binary search the imported English dictionary for any matches.
            $matchIndex = $word_dict->search($term); //binarySearchWord($wordDict, 0, count($wordDict) - 1, $term);

            if ($matchIndex !== -1) {
                // A result was found in the dictionary
                $response['matched'][] = $word_dict->word_at($matchIndex); //$wordDict[$matchIndex]['word'];
                $isValidTerm = true;
                // The dupe count for all pages is 0 for this term.
            }
            else {
                // If the binarySearch didn't find the word, then there has been a mis-spelling.
                $matchIndex = $meta_dict->search(metaphone($term));
                $suggestion_indices = $meta_dict->metaphone_walk($matchIndex);
                $suggestions = $meta_dict->indices_to_words($suggestion_indices);

                //$suggestions_unsorted = getAllMetaphones($metaDict, 0, count($metaDict) - 1, metaphone($term));

                if (count($suggestions) > 0) {
                    $response['suggestions'] = $suggestions; // Before the suggestions got sorted

                    $suggestions = sortSuggestions($suggestions, $term);
                    $response['suggestions_sorted'] = $suggestions; // After the suggestions got sorted
                }
                
                // If there are no suggestions for what the word can be, we must ignore the search term and continue on.
            }
        }

        // In case neither of these cases are true, move onto the next term and ignore this one.
        if ($isValidTerm) {
            $max = $results[0]['dupe_total'];

            // Add up the relevance score for each page based on keyword occurances on the page.
            foreach ($results as $result) {
                $dupe_total = $result['dupe_total'];
                $page_id = $result['page_id'];
                // Increment the relevance scores of each page.
                if (isset($relevance_scores[$page_id])) {
                    $relevance_scores[$page_id] += ceil(($dupe_total / $max) * 100);
                }
                else {
                    $relevance_scores[$page_id] = ceil(($dupe_total / $max) * 100);
                }
            }
        }
        else if (isset($suggestions[0])) {
            // Attempt to find a suggestion which best matches what the user may have meant to type.
            // If a suggestion is not found in the site's content, then move onto the next suggestion until we find a match in the content or run out of suggestions.
            foreach ($suggestions as $suggestion) {
                // Search through keywords for all pages which contain a matching keyword. We use the first letter of the term to select keywords from the correct table.
                $sql = 'SELECT page_id, dupe_total FROM keywords_' . $suggestion['term'][0] . ' WHERE keyword = ? AND site_id = ? ORDER BY dupe_total DESC';
                $statement = $pdo->prepare($sql);
                $statement->execute([$suggestion['term'], $site_id]);
                $results = $statement->fetchAll(); // Returns an array of indexed and associative results.

                if (count($results) > 0) {
                    $max = $results[0]['dupe_total'];

                    // Add up the relevance score for each page based on keyword occurances on the page.
                    foreach ($results as $result) {
                        $dupe_total = $result['dupe_total'];
                        $page_id = $result['page_id'];
                        // Increment the relevance scores of each page.
                        if (isset($relevance_scores[$page_id])) {
                            $relevance_scores[$page_id] += ceil(($dupe_total / $max) * 100);
                        }
                        else {
                            $relevance_scores[$page_id] = ceil(($dupe_total / $max) * 100);
                        }
                    }

                    //$best_suggestions[] = $suggestion;
                    //$best_suggestions[$term] = $suggestion;
                }
            }
           // $response['best_suggestions'] = $best_suggestions;
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
    $phraseHits = [];
    foreach ($results as $result) {
        // Case-insensitive search for the needle (phrase) in the haystack (content)
        $exactMatchIndex = stripos($result['content'], $phrase);
        if ($exactMatchIndex !== false) {
            $relevance_scores[$page_id] += count($terms) * 100;
            $phraseHits[$page_id][] = $exactMatchIndex; // Note the index of where the match was in order to generate a more useful snippet.
        }
    }

    // Put all array keys (aka page_id's) into a separate array.
    $page_ids = array_keys($relevance_scores);

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

    // Create a Result object for each search result found
    //$search_results = [];
    for ($i = 0; $i < count($results); $i++) {
        $page_id = $results[$i]['page_id'];
        $search_results[] = new Result($results[$i]['page_id'],
                                       $urlNoPath . $results[$i]['path'], 
                                       $results[$i]['title'], 
                                       $results[$i]['description'], 
                                       $relevance_scores[$page_id]);
    }

    // Sort the pages by their relevance score
    usort($search_results, 'resultSort');

    $response['relevance_scores'] = $relevance_scores;
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