<?php

// This file will not check whether there is an entry that already exists with the same base_url.
// Prior logic must determine that this site is not currently within the database.

/////////////////////////
// PRE-INITIALIZATION //
///////////////////////

// Begin timer
$begin = round(microtime(true) * 1000);
set_time_limit(240);

// Override PHP.ini so that errors do not display on browser.
ini_set('display_errors', 0);

// Import necessary classes.
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__.'\\PHP\\classes\\crawl_classes.php');

// Import necessary functions.
require_once(__ROOT__.'\\PHP\\functions\\functions.php');
require_once(__ROOT__.'\\PHP\\functions\\crawl_functions.php');

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
  'inserted_into_paragraphs' => false,
  'inserted_into_headers' => false,
  'curl_error' => NULL,
  'pdo_error' => NULL,
  'db_error' => NULL,
  'misc' => []
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
  foreach ($urls as $index => $url) {
    // Create a Page object and build it up each iteration.
    $path = str_replace($base_url, '/', $url);
    $page = new Page($path);
  //for ($i = 0; $i < 3; $i++) {
    // Begin cURL session
    //$curl_session = curl_init();
    //curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true); // Prevent curl_exec from echoing output.
    //curl_setopt($curl_session, CURLOPT_USERAGENT, $agent);
    curl_setopt($curl_session, CURLOPT_URL, str_replace(" ", "%20", $url));
    $html = curl_exec($curl_session);
    // In case there are broken pages on the website, skip those pages and move on.
    if ($html === null) {
      continue;
    }
    $dom = new DOMDocument(); // Create a new DOMDocument object which will be used for parsing through the html
    @ $dom->loadHTML($html); // @ surpresses any warnings

    // Remove the header and the footer since their contents will just bloat the database.
    $header = $dom->getElementById('hcontainer');
    if ($header !== null) {
        $header->parentNode->removeChild($header);
    }
    $footer = $dom->getElementById('fcontainer');
    if ($footer !== null) {
        $footer->parentNode->removeChild($footer);
    }

    // Grab all headers to be used in finding all keywords
    /*$title_keywords = get_keywords_from_tag($dom, 'title');
    $h1_keywords = get_keywords_from_tag($dom, 'h1');
    $h2_keywords = get_keywords_from_tag($dom, 'h2');
    $h3_keywords = get_keywords_from_tag($dom, 'h3');
    $h4_keywords = get_keywords_from_tag($dom, 'h4');*/

    // Grab all content to extract all keywords
    $keywords = get_keywords_from_all($dom);
    //$response['misc'] = get_all_content($dom);
    //$page_content = get_all_content($dom);
    //$page->set_content($page_content);

    // Ignore the homepage
    if ($index !== 0) {
      $main_content = get_main_content($dom->getElementById('dm_content'));
      //$headers = get_all_headers($main_content);//get_each_tag_contents($main_content, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
      $text_tags = get_all_content($dom, $main_content);
      //usort($text_tags, 'sort_by_line_num');
      usort($text_tags['headers'], 'sort_by_line_num');
      usort($text_tags['paragraphs'], 'sort_by_line_num');

      $page->set_headers($text_tags['headers']);
      $page->set_paragraphs($text_tags['paragraphs']);
    }
   
    //echo print_r($keywords);
    
    // Shove all keywords into an array, format each entry, and remove/monitor duplicate keywords.
    //$keywords = array_merge($title_keywords, $h1_keywords, $h2_keywords, $h3_keywords, $h4_keywords);
    $keywords = remove_empty_entries($keywords);
    sort($keywords, SORT_STRING);
    $keywords = array_unique_monitor_dupes($keywords);
    $page->set_keywords($keywords);

    // Grab the title of the page and store it.
    $title = '';
    $title_elem = $dom->getElementsByTagName("title");
    if ($title_elem->length > 0) {
      $title = $title_elem->item(0)->textContent;
      $title = trim(sanitize($title));
      $page->set_title($title);
    }

    // Grab the meta description to store it in the page object.
    $desc = get_description($dom);
    $page->set_desc($desc);
    //$tester = $dom->getElementsByTagName('meta');
   // $response['misc'] = $tester->item(1)->textContent;

    // Create a new instance of Page and add it to the pages array
    //$path = str_replace($base_url, '/', $url);
    //$page = new Page($path, $page_content, $keywords, $title, $desc); // changed $page_content to $main_content
    // Add the Page to the array
    $pages[] = $page;
  }

  if (curl_errno($curl_session)) {
    $error_msg = curl_error($curl_session);
    $response['curl_error'] = $error_msg;
  } else {
    $response['got_pages'] = true;
  }
}
$response['all_pages'] = $pages;

curl_close($curl_session);

///////////////////////////
// INSERT INTO DATABASE //
/////////////////////////

// Get credentials for database
$raw_credentials = file_get_contents("../credentials.json");
$credentials = json_decode($raw_credentials);
$pdo = create_pdo($credentials);

try {
  if (!isset($pdo)) {
    throw new Exception("PDO instance is not defined.");
  }
  if ($response['got_sitemap'] !== true) {
    throw new Exception("Failed to retrieve sitemap. Can't create PDO instance.");
  }
  else if ($response['got_pages'] !== true) {
    throw new Exception("Failed to retrieve pages. Can't create PDO instance.");
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
  $sql = 'CREATE TEMPORARY TABLE ' . $credentials->database_name . '.`keywords` ( `page_id` INT UNSIGNED NOT NULL , `site_id` INT UNSIGNED NOT NULL , `keyword` TINYTEXT NOT NULL , `dupe_total` TINYINT UNSIGNED NOT NULL ) ENGINE = InnoDB';
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
      $statement->bindValue($placeholder+2, $page_keywords[$j]->get_text(), PDO::PARAM_STR);
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

  // Insert keywords which start with a number into the keywords_num table
  $sql = 'INSERT INTO keywords_digit SELECT * FROM `keywords` WHERE `keyword` REGEXP \'^[0-9]\'';
  $statement = $pdo->prepare($sql);
  $statement->execute();

  // Indicate that the keywords were inserted into the database successfully
  $response['inserted_into_keywords'] = true;

  // HEADERS TABLE
  // Calculate total number of headers
  $total_headers = 0;
  foreach ($pages as $page) {
    $headers = $page->get_headers();
    if (!empty($headers)) {
      $total_headers += count($headers);
    }
  }

  $response['total_headers'] = $total_headers;

  // HEADERS TABLE
  // Inserts all headers into the database.
  $pdo_str = create_pdo_placeholder_str(4, $total_headers); // Create the PDO string to use so that the correct amount of ?'s are added
  $sql = 'INSERT INTO headers (page_id, site_id, tag, header) VALUES ' . $pdo_str;
  $statement = $pdo->prepare($sql);
  $pdo_entry = 1;
  foreach ($pages as $page) {
    $headers = $page->get_headers();
    if (!empty($headers)) {
      foreach ($headers as $header) {
        $statement->bindValue($pdo_entry, $page->get_id(), PDO::PARAM_INT);       // Page ID
        $statement->bindValue($pdo_entry+1, $site_id, PDO::PARAM_INT);            // Site ID
        $statement->bindValue($pdo_entry+2, $header->get_tag(), PDO::PARAM_LOB);  // Tag
        $statement->bindValue($pdo_entry+3, $header->get_text(), PDO::PARAM_LOB); // Header
        $pdo_entry += 4;
        //$header->set_id(($pdo_entry / 4) - 1); // Set an ID for each header so that the header and the header's paragraph can get paired up in the database on the next sql call.
      }
    }
  }
  $statement->execute();

  $response['inserted_into_headers'] = true;

  // HEADERS TABLE
  // Grab first entry from the header insertion call.
  $sql = 'SELECT MIN(header_id) FROM headers where site_id = ' . $site_id;
  $statement = $pdo->prepare($sql);
  $statement->execute();
  $sql_res = $statement->fetch(); // Returns an array of indexed and associative results. Indexed is preferred.
  $first_header_id = $sql_res[0];

  $response['first_header_id'] = $first_header_id;

  // Figure out how many paragraphs there are
  $total_paragraphs = 0;
  foreach ($pages as $page) {
    $paragraphs = $page->get_paragraphs();
    if (!empty($paragraphs)) {
      $total_paragraphs += count($paragraphs);
    }
  }

  // PARAGRAPHS TABLE
  // Inserts all paragraphs into the database.
  $pdo_str = create_pdo_placeholder_str(3, $total_paragraphs); // Create the PDO string to use so that the correct amount of ?'s are added
  $sql = 'INSERT INTO paragraphs (page_id, site_id, paragraph) VALUES ' . $pdo_str;
  $statement = $pdo->prepare($sql);
  $pdo_entry = 1;
  foreach ($pages as $page) {
    $paragraphs = $page->get_paragraphs();
    if (!empty($paragraphs)) {
      foreach ($paragraphs as $paragraph) {
          $statement->bindValue($pdo_entry, $page->get_id(), PDO::PARAM_INT);          // Page ID
          $statement->bindValue($pdo_entry+1, $site_id, PDO::PARAM_INT);                 // Site ID
          $statement->bindValue($pdo_entry+2, $paragraph->get_text(), PDO::PARAM_LOB); // Paragraph
          $pdo_entry += 3;
      }
    }
  }
  $statement->execute();
 
  $response['inserted_into_paragraphs'] = true;

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