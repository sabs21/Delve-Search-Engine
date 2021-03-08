<?php
// This file will be required in all php files outside of the classes and functions folders.
// Exceptions to this include files which use none of the functions in this file.

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
    $hostname = $credentials->hostname;
    $dbname = $credentials->database_name;
    $dsn = "mysql:dbname=".$dbname.";host=".$hostname;

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
?>