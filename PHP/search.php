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

// Override PHP.ini so that errors display in logs.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Import necessary classes.
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__.'/delve/classes/classes.php');
require_once(__ROOT__.'/delve/classes/search_classes.php');

// Import necessary functions.
require_once(__ROOT__.'/delve/functions/functions.php');
require_once(__ROOT__.'/delve/functions/search_functions.php');

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
$is_defined_get_request = isset($_GET) && isset($_GET['phrase']) && isset($_GET['url']) && isset($_GET['page']) && isset($_GET['filter_symbols']) && !empty($_GET);
//$is_defined_get_request = isset($_GET) && isset($_GET['phrase']) && isset($_GET['url']) && isset($_GET['page']) && isset($_GET['filter_symbols']) && isset($_GET['redirect_to']) && !empty($_GET);

if ($is_defined_get_request) {
    /*if ($_GET["redirect_to"] !== false) {
        header("Location: " . $_GET['redirect_to']); // When the user needs to redirect the user before showing search results, this header will be applied to the response.
    }*/
    $filter_symbols = filter_var(trim($_GET['filter_symbols']), FILTER_VALIDATE_BOOLEAN);
    // Trim and filter/sanitize the $_GET string data before formatting.
    if (!$filter_symbols) {
        $phrase = strtolower( sanitize( trim($_GET['phrase']), ['symbols' => false, 'lower' => false, 'upper' => false] ) );
    }
    else {
        $phrase = strtolower( sanitize( trim($_GET['phrase']), ['symbols' => true, 'lower' => false, 'upper' => false] ) );
    }
    $url = filter_var( trim($_GET['url']), FILTER_SANITIZE_URL );
    $page_to_return = (int) filter_var( trim($_GET['page']), FILTER_SANITIZE_NUMBER_INT );

    //array_filter($_GET, 'trim_value'); // the data in $_GET is trimmed
    $phrase = str_to_phrase($phrase); // Turn the string into a Phrase object
    $url = format_url($url); // Format the url which was recieved so that it does not end in '/'
    $page_to_return = $page_to_return - 1; // This value will be used as an array index.
    
    $response['filtered_symbols'] = $filter_symbols;
    $response['phrase'] = $phrase;
    $response['url'] = $url;
    $response['page'] = $page_to_return + 1;
}

/////////////////////////////////
// PREPARE TO SEARCH DATABASE //
///////////////////////////////

// Get credentials for database
$raw_credentials = file_get_contents("../../credentials.json");
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
    else if (!$is_defined_get_request) {
        $response['phpError'] = $pdo;
        throw new Exception("The request method used is either not a GET request or a parameter is missing. Parameters include: (string) phrase, (string) url, (int) page, (bool) filtered_symbols).");
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
    if (!$filter_symbols) {
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
                if (isset($page['description']) && $page['description'] !== "") {
                    // If no snippet was made already, just use the page description as the snippet.
                    $search_results[$page['page_id']]->add_snippet($page['description'], false);
                }
                else {
                    // If all else fails, display a placeholder snippet text.
                    $search_results[$page['page_id']]->add_snippet("No description available.", false);
                }
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
    if (isset($result_pages[0])) {
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
        $response['tried_adding_search'] = false;
        $sql = 'INSERT INTO searches (site_id, search_phrase) VALUES (?, ?)';
        $statement = $pdo->prepare($sql);
        $statement->bindValue(1, $site_id, PDO::PARAM_INT);
        $statement->bindValue(2, $phrase->to_string(), PDO::PARAM_STR);
        $statement->execute();
        $pdo->commit();
        $response['tried_adding_search'] = true;
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