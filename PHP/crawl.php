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

class Content {
  protected $content;
  protected $page_id;

  public function __construct($page_id, $content) {
    $this->page_id = $page_id;
    $this->content = $content;
  }

  public function get_content() {
    return $this->content;
  }

  public function set_content($new_content) {
    $this->content = $new_content;
  }

  public function get_page_id() {
    return $this->page_id;
  }

  public function set_page_id($new_id) {
    $this->page_id = $new_id;
  }
}

class Page {
  protected $content;
  protected $id;
  protected $keywords;
  protected $path;
  protected $title;
  protected $desc;

  // $keywords is an array of objects.
  // $path is a string
  public function __construct($path, $content, $keywords, $title, $desc) {
    $this->content = $content;
    $this->id = null;
    $this->keywords = $keywords;
    $this->path = $path;
    $this->title = $title;
    $this->desc = $desc;
  }

  public function get_path() {
    return $this->path;
  }

  public function get_content() {
    return $this->content;
  }

  public function get_keywords() {
    return $this->keywords;
  }

  public function get_id() {
    return $this->id;
  }

  public function set_id($new_id) {
    $this->id = $new_id;
  }

  public function get_title() {
    return $this->title;
  }

  public function set_title($new_title) {
    $this->title = $new_title;
  }

  public function get_desc() {
    return $this->desc;
  }

  public function set_desc($new_desc) {
    $this->desc = $new_desc;
  }
}

class Keyword {
  protected $dupe_total = 1;
  protected $keyword = '';

  // $keyword is a string.
  // $dupe_total is an integer.
  public function __construct($keyword, $dupe_total) {
    $this->keyword = $keyword;
    $this->dupe_total = $dupe_total;
  }

  public function get_keyword () {
    return $this->keyword;
  }

  public function get_dupe_total () {
    return $this->dupe_total;
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
  'inserted_into_contents' => false,
  'curl_error' => NULL,
  'pdo_error' => NULL,
  'db_error' => NULL,
  'misc' => NULL
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
  foreach ($urls as $url) {
  //for ($i = 0; $i < 3; $i++) {
    // Begin cURL session
    //$curl_session = curl_init();
    //curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true); // Prevent curl_exec from echoing output.
    //curl_setopt($curl_session, CURLOPT_USERAGENT, $agent);
    curl_setopt($curl_session, CURLOPT_URL, $url);
    $html = curl_exec($curl_session);
    $dom = new DOMDocument(); // Create a new DOMDocument object which will be used for parsing through the html
    @ $dom->loadHTML($html); // @ surpresses any warnings

    // Remove the header and the footer since their contents will just bloat the database.
    $header = $dom->getElementById('hcontainer');
    $header->parentNode->removeChild($header);
    $footer = $dom->getElementById('fcontainer');
    $footer->parentNode->removeChild($footer);

    // Grab all headers to be used in finding all keywords
    /*$title_keywords = get_keywords_from_tag($dom, 'title');
    $h1_keywords = get_keywords_from_tag($dom, 'h1');
    $h2_keywords = get_keywords_from_tag($dom, 'h2');
    $h3_keywords = get_keywords_from_tag($dom, 'h3');
    $h4_keywords = get_keywords_from_tag($dom, 'h4');*/

    // Grab all content to extract all keywords
    $keywords = get_keywords_from_all($dom);
    //$response['misc'] = get_all_content($dom);
    $page_content = get_all_content($dom);
    //echo print_r($keywords);
    
    // Shove all keywords into an array, format each entry, and remove/monitor duplicate keywords.
    //$keywords = array_merge($title_keywords, $h1_keywords, $h2_keywords, $h3_keywords, $h4_keywords);
    $keywords = remove_empty_entries($keywords);
    sort($keywords, SORT_STRING);
    $keywords = array_unique_monitor_dupes($keywords);

    // Grab the title of the page and store it.
    $title = '';
    $title_elem = $dom->getElementsByTagName("title");
    if ($title_elem->length > 0) {
      $title = $title_elem->item(0)->textContent;
      $title = trim(sanitize($title));
    }

    // Grab the meta description to store it in the page object.
    $desc = get_description($dom);
    //$tester = $dom->getElementsByTagName('meta');
   // $response['misc'] = $tester->item(1)->textContent;

    // Create a new instance of Page and add it to the pages array
    $path = str_replace($base_url, '/', $url);
    $page = new Page($path, $page_content, $keywords, $title, $desc);
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
$password = $creds->password;
$serverIp = $creds->server_ip;
$dbname = $creds->database_name;
$dsn = "mysql:dbname=".$dbname.";host=".$serverIp;

// Create a PDO object to prevent against SQL injection attacks
try {
  if ($response['got_sitemap'] !== true) {
    throw new Exception("Failed to retrieve sitemap. Can't create PDO instance.");
  }
  else if ($response['got_pages'] !== true) {
    throw new Exception("Failed to retrieve pages. Can't create PDO instance.");
  }

  $pdo = new PDO($dsn, $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Errors placed in "S:\Program Files (x86)\XAMPP\apache\logs\error.log"
} catch (PDOException $e) {
  //echo 'Connection failed: ' . $e->getMessage();
  $response['pdo_error'] = 'Connection failed: ' . $e->getMessage();
} catch(Exception $e) {
  $response['pdo_error'] = $e->getMessage();
}

try {
  if (!isset($pdo)) {
    throw new Exception("PDO instance is not defined.");
  }

  // SITES TABLE
  // Delete any related entry that was previously there.
  $pdo->beginTransaction();
  $sql = 'DELETE FROM `sites` WHERE url = ?';
  $statement = $pdo->prepare($sql);
  $statement->execute([$urls[0]]);

  // SITES TABLE
  // Inserts a new site into the database
  $totalPages = count($pages);
  $sql = 'INSERT INTO sites (url, total_pages) VALUES (?, ?)';
  $statement = $pdo->prepare($sql);
  $statement->execute([$urls[0], $totalPages]);

  // Log the success of the site insertion
  $response['inserted_into_sites'] = true;
  
  // SITES TABLE
  // Grab relevant site_id from recent call
  $sql = 'SELECT site_id FROM sites WHERE url = ?';
  $statement = $pdo->prepare($sql);
  $statement->execute([$urls[0]]);
  $sql_res = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
  $site_id = $sql_res[0];

  // Log the success of the site_id selection
  $response['found_site_id'] = true;
  
  // PAGES TABLE
  // Inserts all new pages into the database.
  $pdo_str = create_pdo_placeholder_str(4, $totalPages); // Create the PDO string to use so that the correct amount of ?'s are added
  $sql = 'INSERT INTO pages (site_id, path, title, description) VALUES ' . $pdo_str;
  $statement = $pdo->prepare($sql);
  for ($i = 0, $j = 1; $i < $totalPages; $i++, $j = $j + 4) {
    $statement->bindValue($j, $site_id, PDO::PARAM_INT);
    $statement->bindValue($j+1, $pages[$i]->get_path(), PDO::PARAM_STR);
    $statement->bindValue($j+2, $pages[$i]->get_title(), PDO::PARAM_LOB);
    $statement->bindValue($j+3, $pages[$i]->get_desc(), PDO::PARAM_LOB);
  }
  $statement->execute();

  // Log the success of the mass insertion of pages
  $response['inserted_into_pages'] = true;

  // Calculate and store all page ID's of recently inserted pages
  $firstPageId = $pdo->lastInsertId(); // PHP and mySQL is odd in that lastInsertId actually returns the first inserted id.
  $lastPageId = $firstPageId + ($totalPages - 1);
  for ($i = $firstPageId, $j = 0; $i <= $lastPageId; $i++, $j++) { // Count up from the first page added to the last page added. Assumes no interruptions inbetween entries.
    $pages[$j]->set_id($i);
  }

  // Get the total number of keywords acquired.
  $totalKeywords = 0;
  for ($i = 0; $i < $totalPages; $i++) {
    $totalKeywords += count($pages[$i]->get_keywords());
  }

  // Create a temporary table called keywords.
  // This table will be used to send keywords to different tables based on what letter they start with.
  // I.e., A keyword 'melon' will be sent to the keywords_m table.
  $sql = 'CREATE TEMPORARY TABLE `duda_search_dirty`.`keywords` ( `page_id` INT UNSIGNED NOT NULL , `site_id` INT UNSIGNED NOT NULL , `keyword` TINYTEXT NOT NULL , `dupe_total` TINYINT UNSIGNED NOT NULL ) ENGINE = InnoDB';
  $statement = $pdo->prepare($sql);
  $statement->execute();
  
  // KEYWORDS TABLE
  // Add keywords for each page into the #keywords temporary table.
  $placeholder_str = create_pdo_placeholder_str(4, $totalKeywords); // Create the PDO string to use so that the correct amount of ?'s are added
  $sql = 'INSERT INTO `keywords` (page_id, site_id, keyword, dupe_total) VALUES ' . $placeholder_str;
  $statement = $pdo->prepare($sql);
  $placeholder = 1;
  for ($i = 0; $i < $totalPages; $i++) {
    $page_keywords = $pages[$i]->get_keywords();
    $page_id = $pages[$i]->get_id();
    for ($j = 0; $j < count($page_keywords); $j++) {
      $statement->bindValue($placeholder, $page_id, PDO::PARAM_INT);
      $statement->bindValue($placeholder+1, $site_id, PDO::PARAM_INT);
      $statement->bindValue($placeholder+2, $page_keywords[$j]->get_keyword(), PDO::PARAM_STR);
      $statement->bindValue($placeholder+3, $page_keywords[$j]->get_dupe_total(), PDO::PARAM_INT);
      $placeholder += 4;
    }
  }
  $statement->execute();

  // Insert keywords into their respective tables based on the letter they start with.
  for ($i = 0; $i < 26; $i++) {
    $current_letter = chr(97 + $i); 
    $table = 'keywords_' . $current_letter;
    $sql = 'INSERT INTO keywords_' . $current_letter . ' SELECT * FROM `keywords` WHERE Left(keyword, 1) = ?';
    $statement = $pdo->prepare($sql);
    $statement->execute([$current_letter]);
  }

  // Indicate that the keywords were inserted into the database successfully
  $response['inserted_into_keywords'] = true;

  // CONTENTS TABLE
  // Inserts all new page content into the database.
  $pdo_str = create_pdo_placeholder_str(3, $totalPages); // Create the PDO string to use so that the correct amount of ?'s are added
  $sql = 'INSERT INTO contents (page_id, site_id, content) VALUES ' . $pdo_str;
  $statement = $pdo->prepare($sql);
  for ($i = 0, $j = 1; $i < $totalPages; $i++, $j = $j + 3) {
    $statement->bindValue($j, $pages[$i]->get_id(), PDO::PARAM_INT);
    $statement->bindValue($j+1, $site_id, PDO::PARAM_INT);
    $statement->bindValue($j+2, $pages[$i]->get_content(), PDO::PARAM_LOB);
  }
  $statement->execute();

  // Indicate that the contents of each page was inserted into the database successfully
  $response['inserted_into_contents'] = true;

  $pdo->commit();
} catch (Exception $e) {
  // One of our database queries have failed.
  // Print out the error message.
  //echo $e->getMessage();
  $response['db_error'] = $e->getMessage();
  // Rollback the transaction.
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
}

// Monitor program performance using this timer
$end = round(microtime(true) * 1000);
$response['time_taken'] = $end - $begin;

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
  $excludes = ['the', 'and', 'i', 'was', 'a', 'to', 'we', 'us', 'in', 'our', 'of', 'for', 'that', 'they', 'on', 'this', 'can', 'be']; // Don't consider these as keywords
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
      $isValid = true;
      foreach($excludes as $exclude) {
        if($exclude == $keyword) 
        {
          $isValid = false;
          break;
        }
      }
      if ($isValid) {
        $all_keywords[] = $keyword;
      }
    }
  }
  return $all_keywords;
}

// Input: DOMDocument Object
// Output: Keywords
// Takes the DOMDocument Object and finds all instances of duda paragraph elements.
// This essentially combs all the content within the page for keywords.
function get_keywords_from_all($dom) {
  $excludes = ['an', 'as', 'by', 'the', 'and', 'i', 'was', 'a', 'to', 'like', 'we', 'us', 'in', 'our', 'of', 'for', 'from', 'that', 'they', 'on', 'this', 'can', 'so', 'be', 'it', 'its', 'is', 'if', 'or', 'at', 'you', 'your', 'are', 'when', 'with', 'will']; // Don't consider these as keywords
  // Use DOMXpath to grab content from each element with the data-element-type="paragraph" attribute.
  //$xpath = new DOMXpath($dom);
  //$content_blocks = $xpath->query("//*[contains(@data-element-type,'paragraph')]");
  $content = get_all_content($dom);
  $all_keywords = [];

  //foreach ($content_blocks as $block) {
    // Get the text content and seperate each (formatted) word into an array
    //$content = " " . $block->nodeValue; // Adding the space at the start prevents blocks of content from 'sticking' together
  $options = ['symbols' => true, 'lower' => false, 'upper' => true]; // Options for the sanitize function
  $content = sanitize($content, $options);
  $content = strtolower($content);
  $content_keywords = explode(' ', $content);
  // Ensure each keyword is longer than 1 character and is not a word from the excludes array
  foreach ($content_keywords as $keyword) {
    $isValid = true;
    if (isset($keyword[1])) { // If the word is longer than 1 letter, then it can be considered a keyword.
      foreach($excludes as $exclude) {
        if ($exclude == $keyword) {
          $isValid = false;
          continue;
        }
      }
    }
    else {
      $isValid = false;
    }

    if ($isValid) {
      $all_keywords[] = $keyword;
    }
  }
  //}
  return $all_keywords;
}

// Input: DOMDocument object
// Output: String of content.
// Return all content except for the footer/header.
function get_all_content($dom) {
  $content_wrapper = $dom->getElementById("site_content");
  // Get and format the text
  $content = $content_wrapper->textContent;
  $content = strtolower($content);
  return sanitize($content);
}

// Input: String
// Output: String containing only letters and numbers (ASCII)
// Options: [ 'symbols' => bool, 'lower' => bool, 'upper' => bool]. True indicates to remove.
// Removes unknown and unwanted symbols from a given string.
function sanitize($str, $options = ['symbols' => false, 'lower' => false, 'upper' => false]) {
  $symbols_reg = '\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E';
  $lower_reg = '\x61-\x7A';
  //echo $lower_reg;
  $upper_reg = '\x41-\x5A';
  $regexp = '/[\x00-\x1F';

  /*if ($options['symbols'] && $options['upper'] && $options['lower']) {
    $regexp = '/[\x00-\x1F\x80-\xFF]/'
  }*/

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

  //echo '/[\x00-\x1F\x21-\x2F\x3A-\x60\x7B-\x7E\x80-\xFF]/';
  //echo $regexp . "\n";
  return trim(preg_replace($regexp, ' ', $str)); // Remove unwanted characters based on the values in the options array.
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
  $dupe_total = 1;

  for ($i = 0; $i < $arr_len; $i++) {
    // Check if next entry is the same. If so, increment dupe counter
    if ($arr[$i] === $arr[$i + 1]) {
      $create_new_obj = false;
      // If we arrive at the last entry, then check if the last entries are the same.
      // If so then increment dupe count one last time then add that entry to the result array.
      if ($i === $arr_len - 1) {
        if ($arr[$i - 1] === $arr[$i]) {
          $dupe_total++;
        }
        $result_arr[] = new Keyword($arr[$arr_len - 1], $dupe_total);
      }
      else {
        $dupe_total++;
      }
    }
    else {
      // If we are at the last index of the array, the last index will be compared with NULL.
      // The next keyword isn't a dupe, so set this flag true.
      $create_new_obj = true;
      $result_arr[] = new Keyword($arr[$i - $dupe_total + 1], $dupe_total);
      $dupe_total = 1;
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

// Input: DOMDocument Object.
// Output: Meta description of the page supplied by the DOMDocument input.
// Grab the meta description.
function get_description($dom) {
  $metas = $dom->getElementsByTagName('meta');

  foreach ($metas as $meta) {
    if (strtolower($meta->getAttribute('name')) == 'description') {
      return trim($meta->getAttribute('content'));
    }
  }

  return false;
}