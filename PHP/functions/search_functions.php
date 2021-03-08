<?php

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

// Input: Two String objects
// Output: -1, 0, 1
// Compares two keywords lexicographically.
function nat_cmp(string $a, string $b) {
    return strnatcasecmp($a, $b);
}

// Input: Two Suggestion objects
// Output: -1, 0, 1
// Compares two Suggestions by their total distance.
function sort_suggestions_by_distance(Suggestion $a, Suggestion $b) {
    $a_dist = $a->get_total_distance();
    $b_dist = $b->get_total_distance();
    if ($a_dist < $b_dist) {
        return -1;
    }
    else if ($a_dist > $b_dist) {
        return 1;
    }
    else {
        return 0;
    }
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

function spell_check_keyword(Keyword $keyword, Dictionary $dictionary) {
    // Ensure the keyword is actually a keyword and is not a number.
    $first_char = $keyword->get_text()[0];
    $section_char = $dictionary->get_section_from_char($first_char);
    if ($section_char === false || $section_char === "digit") {
        return;
    }

    $section = null;
    if ($dictionary->has_section($section_char)) {
        $section = $dictionary->get_section($section_char);
    }
    else {
        throw new Exception("This Dictionary does not have a Section '" . $section_char ."'.");
    }

    $is_english = $section->search($keyword->get_text());
    $is_short = strlen($keyword->get_text()) <= 2;
    if (!$is_english && !$is_short) {
        $keyword->is_misspelled(true);
    }
}

function spell_check_phrase(Phrase $phrase, Dictionary $dictionary) {
    $keywords = $phrase->get_all_keywords();
    // Fill the keywords array with all possible keywords
    try {
        foreach ($keywords as $index => $keyword) {
            spell_check_keyword($keyword, $dictionary);
        }
    }
    catch (Exception $e) {
        echo $e->getMessage();
    }
}

function keyword_predictions(Keyword $keyword, Dictionary $dictionary) {
    // Verify that the Dictionary contains the necessary Section. 
    $first_letter = $keyword->get_text()[0];
    $section = null;
    $section_char = $dictionary->get_section_from_char($first_letter);
    if ($dictionary->has_section($section_char)) {
        $section = $dictionary->get_section($section_char);
    }
    else {
        throw new Exception("This Dictionary does not have a Section '" . $section_char ."'.");
    }

    // Add predictions to the array.
    return $section->similar_to($keyword);
}

function phrase_predictions(Phrase $phrase, Dictionary $dictionary) {
    $keywords = $phrase->get_all_keywords();
    $predictions = [];
    foreach ($keywords as $index => $keyword) {
        if ($keyword->is_misspelled()) {
            // Add predictions to the array.
            $predictions = array_merge($predictions, keyword_predictions($keyword, $dictionary));
        }
    }
    return $predictions;
}

// This takes every Prediction and outputs all possible combinations that are as long as the prediction with the highest get_index() value.
function create_suggestions_from_predictions(Phrase $phrase, array $predictions) {
    $phrases = []; // Holds both partial and full suggestions (held as Keyword objects, not Prediction objects)
    foreach ($phrase->get_all_keywords() as $index => $keyword) {
        // Identify the keywords which belong in the suggestion's current $index.
        $round = []; // Holds prediction strings for this round.
        if ($keyword->is_misspelled()) {
            foreach ($predictions as $prediction) {
                $hasMatchingIndex = $prediction->get_index() === $index;
                $hasMorePossibilities = $prediction->get_index() <= $index;
                if ($hasMatchingIndex && $hasMorePossibilities) {
                    $round[] = $prediction->get_prediction(); // Note that get_prediction() returns a Keyword object.
                }
            }
        }
        else {
            $round[] = $keyword;
        }

        // For every one Phrase within $phrases, copy count($round) Phrases to a new array.
        if (count($phrases) <= 0) {
            foreach($round as $prediction) {
                $phrases[] = new Phrase([$prediction]);
            }
        }
        else {
            // There's at least one Phrase in the $phrases array. 
            $new_phrases = [];
            foreach ($phrases as $partial_phrase) {
                $fragments = []; // Create new array
                foreach ($round as $keyword) {
                    $new_phrase = new Phrase($partial_phrase->get_all_keywords()); // Create new Phrase for each prediction
                    $new_phrase->set_keyword($keyword, $keyword->get_index());
                    $fragments[] = $new_phrase;
                }
                $new_phrases = array_merge($new_phrases, $fragments);
            }
            $phrases = array_merge($phrases, $new_phrases);
        }
    }

    // Once we have every Phrase built within the $phrases array,
    // remove each Phrase which is shorter than the input $phrase,
    // create Suggestions out of valid Phrases, and
    // remove each Suggestion which has a distance of 0.
    $suggestions = [];
    foreach ($phrases as $possible_suggestion) {
        $possible_suggestion->set_text($possible_suggestion->to_string()); // temporary fix for the Phrase objects to_string() issue.
        $has_same_length = $possible_suggestion->length() === $phrase->length();
        $has_same_distance = levenshtein($possible_suggestion->to_string(), $phrase->to_string()) === 0;
        if ($has_same_length && !$has_same_distance) {
            $suggestions[] = new Suggestion($phrase, $possible_suggestion);
        }
    }
    return $suggestions;
}

// Intended for when searching through the paragraphs is the only option. (basically, a forced search is performed)
function fetch_keyword_dupes_from_paragraphs(array $keywords, PDO $pdo, int $site_id) {
    // Build the SQL query
    $sql = 'SELECT * FROM (';
    foreach ($keywords as $keyword) {
        $sql .= 'SELECT `site_id`, `page_id`, ? AS keyword, ROUND ((LENGTH(paragraph) - LENGTH(REPLACE(paragraph, ?, ""))) / LENGTH(?)) AS dupe_total FROM paragraphs WHERE site_id = ? UNION ALL';
    }
    $sql = substr($sql, 0, -9); // Remove the extra UNION ALL from the end.
    $sql .= ') AS data WHERE dupe_total > 0 ORDER BY dupe_total DESC;';
    // Obtain results based on the whole phrase
    //$sql = 'SELECT * FROM (SELECT `site_id`, `page_id`, `?` AS keyword, ROUND ((LENGTH(paragraph) - LENGTH(REPLACE(paragraph, `?`, ""))) / LENGTH(`?`)) AS dupe_total FROM paragraphs WHERE site_id = ?) AS data WHERE dupe_total > 0 ORDER BY dupe_total DESC;';
    $statement = $pdo->prepare($sql);
    for ($i = 0, $j = 1, $keyword_count = count($keywords); $i < $keyword_count; $i++) {
        $statement->bindValue($j, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+1, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+2, $keyword->get_text(), PDO::PARAM_STR);
        $statement->bindValue($j+3, $site_id, PDO::PARAM_INT);
        $j += 4;
    }
    $statement->execute();
    return $statement->fetchAll();
}

function fetch_keyword_dupes(array $keywords, PDO $pdo, int $site_id) {
    $keyword_strings = []; // Used to hold Prediction and Keyword text

    // Find how many keywords start with each letter.
    $totals = []; // Tracks the total number of keywords with the same first letters.
    foreach ($keywords as $keyword) {
        // Verify that this entry within the array is an object.
        if (gettype($keyword) !== "object") {
            trigger_error("Entry within array is a " . gettype($keyword) . ". Must be either a Keyword or Prediction object. Entry has been skipped.", E_USER_WARNING);
            continue;
        }

        // Next, verify that this object is either a Keyword or a Prediction.
        $first_letter = "";
        if (get_class($keyword) === "Keyword") {
            $keyword_strings[] = $keyword->get_text();
            $first_letter = $keyword->get_text()[0];
        }
        else if (get_class($keyword) === "Prediction") {
            $keyword_strings[] = $keyword->get_prediction()->get_text();
            $first_letter = $keyword->get_prediction()->get_text()[0];
        }
        else {
            trigger_error("Entry within array is of the class " . get_class($keyword) . ". Must be either a Keyword or Prediction object. Entry has been skipped.", E_USER_WARNING);
            continue;
        }

        // Increment the total.
        if (isset($totals[$first_letter])) {
            $totals[$first_letter]++;
        }
        else {
            $totals[$first_letter] = 1;
        }
    }

    // Sort the totals and the keyword_strings to ensure a predictable order.
    ksort($totals);
    natcasesort($keyword_strings);
    $keyword_strings = array_values($keyword_strings); // Re-index the keys so that the entires of $keyword_strings array are ordered as expected.

    // If totals is empty, then we have nothing to search!
    if (empty($totals)) {
        return [];
    }

    // Generate the SQL string
    $sql = '';
    foreach ($totals as $first_letter => $total) {
        $pdo_str = create_pdo_placeholder_str($total, 1);
        $number_regex = "/[0-9]/";
        $is_number = preg_match($number_regex, $first_letter);
        if ($is_number) {
            $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_num WHERE site_id = ? AND keyword IN ' . $pdo_str . ' union ALL ';
        }
        else {
            $sql .= 'SELECT page_id, keyword, dupe_total FROM keywords_' . $first_letter . ' WHERE site_id = ? AND keyword IN ' . $pdo_str . ' union ALL ';
        }
    }

    // Replace 'union ALL' with an ORDER BY clause
    $sql = substr($sql, 0, -10);
    $sql .= 'ORDER BY dupe_total DESC'; 

    // Prepare and return the PDO statement
    $statement = $pdo->prepare($sql);
    $sum = 1; // Used for binding values to the correct indices.
    $index = 0; // Used for tracking which keyword we are on. 
    foreach ($totals as $total) {
        $statement->bindValue($sum, $site_id, PDO::PARAM_INT);
        $sum++;
        for ($i = $index; $i < $total + $index; $i++) {
            $statement->bindValue($sum, $keyword_strings[$i], PDO::PARAM_STR);
            $sum++;
        }
        //foreach ($keyword_strings as $keyword_string) {
        //    $statement->bindValue($sum, $keyword_string, PDO::PARAM_STR);
        //    $sum++;
        //}
        $index += $total;
    }
    $statement->execute();
    return $statement->fetchAll();
}

function fetch_phrase_dupes(Phrase $phrase, PDO $pdo, int $site_id) {
    // If there are not enough keywords, then return a blank array.
    if ($phrase->length() < 2) {
        return [];
    }

    // Obtain results based on the whole phrase
    $sql = 'SELECT page_id, paragraph FROM paragraphs WHERE site_id = ? AND INSTR(paragraph, ?)';
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase->to_string()]);
    return $statement->fetchAll();
}

function get_relevance(int $dupe_total, int $max) {
    return ceil(($dupe_total / $max) * 100);
}

// Relies on the keyword_results being ordered by the dupe_total in descending order.
function rank_results($keyword_results, $phrase_results) {
    // Obtain all relevance scores and populate array of Results
    //$search_results = []; // Contains all Results objects.
    $maxes = []; // Holds each keywords highest dupe totals.
    $score_keeper = new ScoreKeeper();

    if ($keyword_results !== NULL) {
        // Add to score based on individual keywords
        foreach ($keyword_results as $result) {
            //$page_id = $result['page_id'];
            //$search_results[$page_id] = new Result($page_id);
            if (!isset($maxes[$result['keyword']])) {
                $maxes[$result['keyword']] = $result['dupe_total'];
            }
            $score = get_relevance($result['dupe_total'], $maxes[$result['keyword']]);
            $score_keeper->add_to_score($score, $result['page_id']);
            /*foreach ($phrase->get_all_keywords() as $original_keyword) {
                $keywords_match = $result['keyword'] === $original_keyword->get_text();
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
            }*/
        }
    }
    
    if ($phrase_results !== NULL) {
        // Add to score based on the whole phrase.
        foreach ($phrase_results as $result) {
            // Case-insensitive search for the needle (phrase) in the haystack (content)
            //$phraseMatchIndex = stripos($result['paragraph'], $phrase);
            //if ($phraseMatchIndex !== false) {
                $inflated_score = count($maxes) * 100;
                $score_keeper->add_to_score($inflated_score, $result['page_id']); //[$result['page_id']] += count($keywords) * 100;
            //}
        }
    }
    return $score_keeper;
}

function generate_snippet($phrase, $matched_paragraph) {
    // $matched_paragraph is a paragraph that contains the phrase as a substring.
    // Generate snippets for any results whose content contains the search phrase.

    // Case-insensitive search for the needle (phrase) in the haystack (content)
    $phraseMatchIndex = stripos($matched_paragraph['paragraph'], $phrase->to_string());

    $paragraph_length = strlen($matched_paragraph['paragraph']);
    $charsFromPhrase = 140; // Amount of characters around the phrase to capture for the snippet.
    $clipsAtStart = $phraseMatchIndex < $charsFromPhrase; // Check if we can get 140 characters before the phrase without going below zero.
    $clipsAtEnd = $phraseMatchIndex + $charsFromPhrase > $paragraph_length; // Check if we can get 140 characters after the phrase without going past the snippet length.
    $snippetStart = $phraseMatchIndex - $charsFromPhrase; // Starting index of the snippet
    $distance_to_end = $paragraph_length - $snippetStart;
    $idealLength = $charsFromPhrase * 2; // The ideal length of the snippet.
    if ($clipsAtStart) {
        $snippetStart = 0;
    }
    if ($clipsAtEnd) {
        $idealLength = $distance_to_end;
    }
    
    // Ensures whole word is captured on the beginning edge.
    $is_space_char = ord($matched_paragraph['paragraph'][$snippetStart]) === 32;
    while ($snippetStart > 0) {
        if (!$is_space_char) {
            $snippetStart--;
        }
        else {
            $snippetStart++; // This removes the space from the start of the snippet.
            break;
        }
        $is_space_char = ord($matched_paragraph['paragraph'][$snippetStart]) === 32;
    }
    $snippet = substr($matched_paragraph['paragraph'], $snippetStart, $distance_to_end); // Cut the beginning of the paragraph where the snippet will start.
    
    // Ensures whole word is captured on the ending edge.
    if (strlen($snippet) > $idealLength) {
        $snippet = wordwrap($snippet, $idealLength);
        $snippet = substr($snippet, 0, strpos($snippet, "\n"));
    }

    // Remove line breaks from snippet.
    $br_regex = "/<br>/";
    while (preg_match_all($br_regex, $snippet) > 0) {
        // Find the index of the line break
        $match = [];
        preg_match($br_regex, $snippet, $match, PREG_OFFSET_CAPTURE);
        $break_index = $match[0][1];

        // Check whether the line break is to the left or right of the phrase. This info is useful for substringing the snippet properly.
        $phraseIndex = stripos($snippet, $phrase->to_string());
        if ($break_index < $phraseIndex) {
            // <br> is on the left of the phrase. __ signifies the ideal cutoff in the example below.
            // sample text.<br>__New line of text containing phrase...
            $break_index = $break_index + 4;
            $snippet = substr($snippet, $break_index, strlen($snippet) - $break_index);
        }
        else {
            // <br> is on the right of the phrase. __ signifies the ideal cutoff in the example below.
            // sample text containing phrase.__<br>New line of text...
            $snippet = substr($snippet, 0, $break_index);
        }
    }

    // Check if first word is capitalized. If not, add ellipses.
    $capitalized_regex = "/[A-Z]/";
    $is_capitalized = preg_match($capitalized_regex, $snippet[0]);
    if (!$is_capitalized) {
        $snippet = "... " . $snippet;
    }

    // Check if the last word ends with punctuation. If not, add ellipses.
    $punctuation_regex = "/[.!?]/";
    $is_stopped = preg_match($punctuation_regex, $snippet[strlen($snippet) - 1]);
    if (!$is_stopped) {
        $snippet = $snippet . "...";
    }

    //$search_results[$matched_paragraph['page_id']]->add_snippet($snippet, true);
    return $snippet;
}

function fetch_all_paths_and_metadata(array $page_ids, PDO $pdo) {
    // To comunicate with the database as few times as possible, 
    // this SQL query gets filled with all of the page_id's that we need info for.
    $pdo_str = create_pdo_placeholder_str(count($page_ids), 1);
    $sql = 'SELECT page_id, path, title, description FROM pages WHERE page_id IN ' . $pdo_str;
    $statement = $pdo->prepare($sql);
    for ($i = 0; $i < count($page_ids); $i++) {
        $statement->bindValue($i+1, $page_ids[$i], PDO::PARAM_INT);
    }
    $statement->execute();
    return $statement->fetchAll();
}
?>