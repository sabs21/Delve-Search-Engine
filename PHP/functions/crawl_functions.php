<?php

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
    $content = get_all_keywords($dom);
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
// Useful for obtaining all keywords.
function get_all_keywords($dom) {
    $content_wrapper = $dom->getElementById("site_content");
    // Get and format the text
    $content = $content_wrapper->textContent;
    $content = strtolower($content);
    return sanitize($content);
}
  
// Input: DomDocument Element
// Output: Node
// Find element containing the most text which is:
// 1) As deep as possible in the DOM.
// 2) Contains headers and paragraphs.
/*function get_main_content($top) {
    $winner = $top;
    $children = null;
  
    // Start from the $top element. Scan each sibling to see which contains the most text and save it.
    // When the winning element is found, save it and its level in the DOM, then move to its children and repeat.
    while ($winner->hasChildNodes()) {
        $children = $winner->childNodes;
        $max = 0;
        // This loop picks the element with the most text as the winner.
        foreach($children as $element) {
            // If this element is a text element, then we have found the deepest winner
            if ($element->hasAttributes()) {
                $is_text_elem = !is_null($element->attributes->getNamedItem("data-element-type"));
                if ($is_text_elem) {
                    // Return the winner found in the previous iteration.
                    // NOTE: We can't simply call $winner since we could've overwritten it this iteration.
                    return $element->parentNode;
                }
            }
  
            $length = strlen($element->textContent);
            if ($max < $length) {
                $max = $length;
                $winner = $element;
            }
        }
    }
    return $winner;
}*/

// Input: DomDocument Element
// Output: Node
// Find element containing the most text which is:
// 1) As deep as possible in the DOM.
// 2) Contains headers and paragraphs.
function get_main_content($top) {
    $winner = $top;
    $children = null;
  
    // Start from the $top element. Scan each sibling to see which contains the most text and save it.
    // When the winning element is found, save it and its level in the DOM, then move to its children and repeat.
    while ($winner->hasChildNodes()) {
        $children = $winner->childNodes;
        $max = 0;
        // This loop picks the element with the most text as the winner.
        foreach($children as $element) {
            // If this element is a text element, then we have found the deepest winner
            if ($element->hasAttributes()) {
                if (!is_null($element->attributes->getNamedItem("data-element-type"))) {
                    $is_text_elem = $element->attributes->getNamedItem("data-element-type") == "paragraph";
                    if ($is_text_elem) {
                        // Return the winner found in the previous iteration.
                        // NOTE: We can't simply call $winner since we could've overwritten it this iteration.
                        return $element->parentNode;
                    }
                }  
            }
  
            $length = strlen($element->textContent);
            if ($max < $length) {
                $max = $length;
                $winner = $element;
            }
        }
    }
    return $top; // If no paragraph elements were found, this page likely relies on headers for text content.
}
  
// Input: DOMDocument Element
//        Tag array
// Output: Array
// Get all text within the specified tags.
function get_each_tag_contents($element, $tags = ['p']) {
    $contents = [];
    // For each tag, extract all text content.
    foreach ($tags as $tag) {
        $nodes = $element->getElementsByTagName($tag);
        foreach ($nodes as $tag_elem) {
            // This if statement prevents blank entries.
            if (!empty($tag_elem->textContent)) {
                $contents[$tag][] = $tag_elem->textContent;
            }
        } 
    }
    return $contents;
}
  
// Input: DOMDocument Element
// Output: Array of Header objects
// Get all headers and their associated paragraphs.
/*function get_all_headers($element) {
    $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $headers = [];
    // For each header tag, extract all text content.
    foreach ($tags as $tag) {
        $nodes = $element->getElementsByTagName($tag);
        foreach ($nodes as $elem) {
            // This if statement prevents blank entries.
            if (!empty($elem->textContent)) {
                $new_header = new Header($elem, $tag);
                // Now that we have the header, find the paragraph associated with this header.
                $nextElem = $elem->nextSibling;
                while (!is_null($nextElem)) {
                    if ($nextElem->hasAttributes()) {
                        // If the next sibling is not a header AND is a text element (evidenced by the 'data-element-type' attribute), 
                        // then we have found a paragraph which is associated with this header.
                        $is_text_elem = !is_null($nextElem->attributes->getNamedItem("data-element-type"));
                        if ($is_text_elem) {
                            $is_header = $nextElem->tagName === 'h1' && $nextElem->tagName === 'h2' && $nextElem->tagName === 'h3' && $nextElem->tagName === 'h4' && $nextElem->tagName === 'h5' && $nextElem->tagName === 'h6';
                            if (!$is_header) {
                                $paragraph = trim($nextElem->textContent);
                                $paragraph = preg_replace('/\s\s+/', ' ', $paragraph); // Remove excess whitespace
                                if ($paragraph !== '' && strlen($paragraph) > 100) {
                                    // We now know this is a valid paragraph.
                                    // Add in where new lines should be, then set the paragraph.
                                    $newLineRegex = "/[^ \.][.!?][^ )\.]/";
                                    $matches = [];
                                    preg_match_all($newLineRegex, $paragraph, $matches, PREG_OFFSET_CAPTURE); // PREG_OFFET_CAPTURE allows me to get the indices of each match.
                                    $matches = $matches[0]; // Bypass an unnecessary layer of the array.
                                    for ($i = 0; $i < count($matches); $i++) {
                                        $match = $matches[$i][1];
                                        $matchIndex = $match + (2 + (4 * $i)); // With each index match, there's a 2 character offset to the left due to the regex. Also, for each <br> added, the length of the string increases by 4, so we must add 4 to each concurrent match in the $matches array.
                                        $paragraph = substr_replace($paragraph, "<br>", $matchIndex, 0);
                                    }
                                    $new_header->set_paragraph($paragraph);
                                }
                            }
                            $headers[] = $new_header;
                            break;
                        }
                    }
                    $nextElem = $nextElem->nextSibling;
                }
            }
        } 
    }
    return $headers;
}*/

// Input: DOMDocument
//        DOMElement
// Output: Array of Content objects
// Get all headers and their associated paragraphs.
function get_all_content($dom, $element) {
    $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'];
    $headers = [];
    $paragraphs = [];

    foreach ($tags as $tag) {
        $nodes = $element->getElementsByTagName($tag);
        foreach ($nodes as $node) {
            $text = str_replace(array("\r", "\n"), " ", $node->textContent); // Remove line breaks, carriage returns, and trim around the text.
            $text = preg_replace("/[^\p{L}\p{P}\p{N}]/u", " ", $text); // Remove all non-printable characters. // /[^\x20-\x7E]+/
            $text = trim(preg_replace("/  +/", " ", $text)); // Convert long sequences of spaces to a single space.
            $line_num = $node->getLineNo();
            if (empty($text)) {
                continue;
            }

            // Check to see if this element is a duda text widget or a duda custom widget.
            $is_text_widget = false;
            $is_custom_widget = false;
            $has_element_type = false;
            if ($node->hasAttributes()) { // Has attributes
                $is_content_container = false;
                if ($node->attributes->getNamedItem("id")) { // Has ID
                    $is_content_container = $node->attributes->getNamedItem("id")->nodeValue == 'dm_content';
                }
                $has_element_type = $node->attributes->getNamedItem("data-element-type") != null;
            }
            if ($has_element_type) {
                $is_text_widget = $node->attributes->getNamedItem("data-element-type")->nodeValue == "paragraph";
                $is_custom_widget = $node->attributes->getNamedItem("data-element-type")->nodeValue == "custom_extension";
            }
            if ($is_text_widget || $is_custom_widget) { //($is_text_widget || $is_custom_widget) && $tag == 'div'
                // Fix any cases where two words are "glued" together. I.e., "asphaltTrailers" should be "asphalt Trailers"
                // We must ensure that our keywords are actually useful since the content from the site will act as our dictionary.
                $camelCaseRegex = "/[a-z:;)](?=[A-Z])/";
                $gluedWords = [];
                preg_match_all($camelCaseRegex, $text, $gluedWords, PREG_OFFSET_CAPTURE); // PREG_OFFET_CAPTURE lets us get the indices of each match.
                $gluedWords = $gluedWords[0]; // Bypass an unnecessary layer of the array.
                $offset = 0;
                foreach ($gluedWords as $i => $gluedWord) {
                    $text = substr_replace($text, $gluedWord[0] . " ", $gluedWord[1] + $offset, 1);
                    $offset++;
                }

                $highest_tag = get_highest_tag_from_children($dom, $node); // Find any headers that may be within the div.
                if ($highest_tag == 'div' || $highest_tag == 'p') {
                    $paragraphs[] = new Content($text, $highest_tag, $line_num);
                }
                else {
                    $headers[] = new Content($text, $highest_tag, $line_num);
                }
            }
        }
    }
    return ['headers' => $headers, 'paragraphs' => $paragraphs];
}

// Input: Two Content or Paragraph objects
// Output: Integer signifying whether A is less than, equal to, or greater than B
// Compare Content/Paragraph A with Content/Paragraph B
function sort_by_line_num($elemA, $elemB) {
    $a = $elemA->get_line_num();
    $b = $elemB->get_line_num();

    if ($a === $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

// Input: DOMNode
// Output: The highest tag
function get_highest_tag_from_children($dom, $node) {
    $html = $dom->saveHTML($node);
    //return $html;
    $text_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];
    $tag_regexps = ["/<h1/", "/<h2/", "/<h3/", "/<h4/", "/<h5/", "/<h6/", "/<p/"];
    foreach ($tag_regexps as $tag_index => $regexp) {
        if (preg_match($regexp, $html)) {
            return $text_tags[$tag_index];
        }
    }
    return 'div';
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
                $result_arr[] = new Token($arr[$arr_len - 1], $dupe_total);
            }
            else {
                $dupe_total++;
            }
        }
        else {
            // If we are at the last index of the array, the last index will be compared with NULL.
            // The next keyword isn't a dupe, so set this flag true.
            $create_new_obj = true;
            $result_arr[] = new Token($arr[$i - $dupe_total + 1], $dupe_total);
            $dupe_total = 1;
        }
    }
    return $result_arr;
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
?>