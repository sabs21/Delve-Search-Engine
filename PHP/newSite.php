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

class Page {
  protected $keywords;
  protected $path;

  // $keywords is an array of objects.
  // $path is a string
  public function __construct($keywords, $path) {
    $this->keywords = $keywords;
    $this->path = $path;
  }

  public function get_keywords() {
    return $this->keywords;
  }

  public function get_path() {
    return $this->path;
  }
}

class Keyword {
  protected $dupe_count = 1;
  protected $keyword = '';

  // $keyword is a string.
  // $dupe_count is an integer.
  public function __construct($keyword, $dupe_count) {
    $this->keyword = $keyword;
    $this->dupe_count = $dupe_count;
  }

  public function get_keyword () {
    return $this->keyword;
  }

  public function get_dupe_count () {
    return $this->dupe_count;
  }
}

/////////////////////
// INITIALIZATION //
///////////////////

$agent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.370.0 Safari/533.4';

// Get data from the POST sent from the fetch API
$raw = trim(file_get_contents('php://input'));
$sitemap_url = json_decode($raw)->sitemap;

// Use this array as a basic response object. May need something more in depth in the future.
// Prepares a response to identify errors and successes.
$response = [
  'time_taken' => 0,
  'got_sitemap' => false,
  'got_pages' => false,
  'inserted_into_sites' => false,
  'found_site_id' => false,
  'inserted_into_pages' => false,
  'inserted_into_keywords' => false,
  'curl_error' => NULL,
  'pdo_error' => NULL,
  'db_error' => NULL
];

// Grab sitemap
$curl_session = curl_init();
curl_setopt($curl_session, CURLOPT_URL, $sitemap_url);
curl_setopt($curl_session, CURLOPT_BINARYTRANSFER, true); // Prevent curl_exec from echoing output.
curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true); // Prevent curl_exec from echoing output.
curl_setopt($curl_session, CURLOPT_USERAGENT, $agent);
$xml_data = curl_exec($curl_session);

$data_arr = explode("<loc>", $xml_data);

// Verify the success (or failure) of grabbing urls from the sitemap
if (gettype($data_arr) === 'array' && count($data_arr) > 0) {
  $response['got_sitemap'] = true;
  //echo print_r($data_arr);
}
else if (curl_errno($curl_session)) {
  $error_msg = curl_error($curl_session);
  $response['curl_error'] = $error_msg;
}
else {
  $response['curl_error'] = 'Cannot retrieve sitemap. Check the url or your connection.';
}

// Get all sitemap url's and put them into array.
// For some reason the 0'th index of $dataArr is blank, so I started $i at 1.
for ($i = 1; $i < count($data_arr); $i++) {
    $url_end_pos = strpos($data_arr[$i], "<") - 1;
    $url = substr($data_arr[$i], 0, $url_end_pos + 1);
    $urls[$i-1] = $url;
}

//////////////////////////////////
// GET KEYWORDS FROM EACH PAGE //
////////////////////////////////

curl_setopt($curl_session, CURLOPT_BINARYTRANSFER, false); // Prevent curl_exec from echoing output.
$base_url = $urls[0];

// Ensure that the base_url is not null so that each path to be inserted into the database is not formatted as a url.
if (!is_null($base_url) && !empty($base_url)) {
  // Loop through and crawl each page to grab content.
  //foreach ($urls as $url) {
  for ($i = 0; $i < 3; $i++) {
    // Begin cURL session
    //$curl_session = curl_init();
    //curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true); // Prevent curl_exec from echoing output.
    //curl_setopt($curl_session, CURLOPT_USERAGENT, $agent);
    curl_setopt($curl_session, CURLOPT_URL, $base_url);
    $html = curl_exec($curl_session);
    $dom = new DOMDocument(); // Create a new DOMDocument object which will be used for parsing through the html
    @ $dom->loadHTML($html); // @ surpresses any warnings

    // Grab all headers to be used in finding all keywords
    $title_keywords = get_keywords_from_tag($dom, 'title');
    $h1_keywords = get_keywords_from_tag($dom, 'h1');
    $h2_keywords = get_keywords_from_tag($dom, 'h2');
    $h3_keywords = get_keywords_from_tag($dom, 'h3');

    // Shove all keywords into an array, format each entry, and remove/monitor duplicate keywords.
    $keywords = array_merge($title_keywords, $h1_keywords, $h2_keywords, $h3_keywords);
    $keywords = remove_empty_entries($keywords);
    sort($keywords, SORT_STRING);
    $keywords = array_unique_monitor_dupes($keywords);

    // Create a new instance of Page and add it to the pages array
    $path = str_replace($base_url, '/', $urls[$i]);
    $page = new Page($keywords, $path);
    $pages[] = $page;
  }

  if (curl_errno($curl_session)) {
    $error_msg = curl_error($curl_session);
    $response['curl_error'] = $error_msg;
  } else {
    $response['got_pages'] = true;
  }
}

curl_close($curl_session);

///////////////////////////
// INSERT INTO DATABASE //
/////////////////////////

// Get credentials for database
$rawCreds = file_get_contents("../credentials.json");
$creds = json_decode($rawCreds);

$username = $creds->username;
$password = $creds->passwords;
$serverIp = $creds->server_ip;
$dbname = $creds->database_name;
$dsn = "mysql:dbname=".$dbname.";host=".$serverIp;

if ($response['got_pages'] === true && $response['got_sitemap'] === true) {
  // Create a PDO object to prevent against SQL injection attacks
  try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
    //echo 'Connection failed: ' . $e->getMessage();
    $response['pdo_error'] = 'Connection failed: ' . $e->getMessage();
  } finally {
    try {
      $pdo->beginTransaction();

      // Inserts a new site into the database
      $totalPages = count($pages);
      $sql = 'INSERT INTO sites (url, total_pages) VALUES (?, ?)';
      $statement = $pdo->prepare($sql);
      $statement->execute([$urls[0], $totalPages]);

      // Log the success of the site insertion
      $response['inserted_into_sites'] = true;

      // Grab relevant site_id from recent call
      //$pdo->beginTransaction();
      $sql = 'SELECT site_id FROM sites WHERE url = ?';
      $statement = $pdo->prepare($sql);
      $statement->execute([$urls[0]]);
      $sql_res = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
      $site_id = $sql_res[0];

      // Log the success of the site_id selection
      $response['found_site_id'] = true;

      // Inserts all new pages into the database.
      $pdo_str = create_pdo_placeholder_str(2, $totalPages); // Create the PDO string to use so that the correct amount of ?'s are added
      $sql = 'INSERT INTO pages (site_id, path) VALUES ' . $pdo_str;
      $statement = $pdo->prepare($sql);
      for ($i = 0, $j = 1; $i < $totalPages; $i++, $j = $j + 2) {
        $statement->bindValue($j, $sql_res[0], PDO::PARAM_INT);
        $statement->bindValue($j+1, $pages[$i]->get_path(), PDO::PARAM_STR);
      }
      $statement->execute();

      // Log the success of the mass insertion of pages
      $response['inserted_into_pages'] = true;

      $pdo->commit();
    } catch (Exception $e) {
      // One of our database queries have failed.
      // Print out the error message.
      //echo $e->getMessage();
      $response['db_error'] = $e->getMessage();
      // Rollback the transaction.
      $pdo->rollBack();
    }
  }
}

// Monitor program performance using this timer
$end = round(microtime(true) * 1000);
$response['time_taken'] = $end - $begin;
//$time_taken = $end - $begin;
//echo "Time taken to execute: " . $timeTaken;

// Send a response back to the client.
echo json_encode($response);

////////////////
// FUNCTIONS //
//////////////

// Input: DOMDocument Object
//        Tag as a string (ex. h1)
// Output: Keywords
// Given an html tag, this function grabs all text within those tags.
function get_keywords_from_tag($dom, $tag) {
  $punctuations = [',', '.', '[', ']', '{', '}', '\'', '"', '(', ')', '\n'];
  $all_keywords = [];
  $tag_arr = $dom->getElementsByTagName($tag);
  foreach($tag_arr as $tag) {
    // Get and format the text
    $tag_text = $tag->textContent;
    $tag_text = str_replace($punctuations, ' ', $tag_text);
    $tag_text = strtolower($tag_text);
    // Separate each word and place inside of a keyword array.
    $tag_keywords = explode(' ', $tag_text);
    foreach($tag_keywords as $keyword) {
      $all_keywords[] = $keyword;
    }
  }
  return $all_keywords;
}

// Input: Any array
// Output: Array without blank entries
// Uses regex to detect and keep good entries while throwing out the rest (the blanks).
function remove_empty_entries($arr) {
  $letter_pattern = '/[a-z0-9]/';
  $remove_newline_pattern = '/\s+/';
  foreach($arr as $item) {
    $item = preg_replace('/\s+/', ' ', trim($item));
    // Use regex to keep entries with alpha-numeric chars and throw out the blank entries.
    if (preg_match($letter_pattern, $item)) {
      $return_arr[] = $item;
    }
  }
  return $return_arr;
}

// Input: A sorted array of strings
// Output: Array containing objects describing the amount of duplicate keywords.
// Make an array of objects where duplicates of keywords are counted and contained
// within unique objects.
function array_unique_monitor_dupes($arr) {
  $result_arr = [];
  $arr_len = count($arr) - 1;
  $create_new_obj = true;
  $dupe_count = 1;

  for ($i = 0; $i < $arr_len; $i++) {
    // Check if next entry is the same. If so, increment dupe counter
    if ($arr[$i] === $arr[$i + 1]) {
      $create_new_obj = false;
      // If we arrive at the last entry, then check if the last entries are the same.
      // If so then increment dupe count one last time then add that entry to the result array.
      if ($i === $arr_len - 1) {
        if ($arr[$i - 1] === $arr[$i]) {
          $dupe_count++;
        }
        $result_arr[] = new Keyword($arr[$arr_len - 1], $dupe_count);
      }
      else {
        $dupe_count++;
      }
    }
    else {
      // If we are at the last index of the array, the last index will be compared with NULL.
      // The next keyword isn't a dupe, so set this flag true.
      $create_new_obj = true;
      $result_arr[] = new Keyword($arr[$i - $dupe_count + 1], $dupe_count);
      $dupe_count = 1;
    }
  }
  return $result_arr;
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
