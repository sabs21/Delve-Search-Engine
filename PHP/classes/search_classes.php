<?php
// A Prediction is an alternate to Keyword
class Prediction {
    public $original; // Keyword object
    public $prediction; // Keyword object
    public $distance;
    public $index;

    public function __construct(Keyword $original, Keyword $prediction) {
        $this->original = $original; // Keyword object
        $this->prediction = $prediction;
        $this->index = $original->get_index();
        $this->distance = levenshtein($original->get_text(), $prediction->get_text());
    }

    public function get_original() {
        return $this->original;
    }

    public function get_prediction() {
        return $this->prediction;
    }

    public function get_index() {
        return $this->index;
    }

    public function get_distance() {
        return $this->distance;
    }
}

class Result {
    public $page_id;
    public $url;
    public $title;
    public $snippets;
    public $relevance;

    public function __construct(int $page_id, string $url = NULL, string $title = NULL, int $relevance = 0) {
        $this->page_id = $page_id;
        $this->url = $url;
        $this->title = $title;
        $this->snippets = [];
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
    public function set_title(string $str) {
        $this->title = $str;
    }

    public function get_snippet($i) {
        return $this->snippets[$i];
    }
    public function get_all_snippets() {
        return $this->snippets;
    }

    public function add_snippet(string $text, bool $from_page_content) {
        $this->snippets[] = ["text" => $text, "fromPageContent" => $from_page_content];
    }

    public function get_relevance() {
        return $this->relevance;
    }
    public function set_relevance(int $value) {
        $this->relevance = $value;
    }
}

// This dictionary is created from the website's keywords.
// The dictionary consists of sections seperated by each keyword's beginning letter.
// This allows for the Dictionary to retain the Section objects that are created.
class Dictionary {
    public $dictionary; // Array of Section objects.
    //public $section; // Letter which all words begin with.

    public function __construct($dictionary = []) {
        $this->dictionary = $dictionary;
    }

    // All dictionaries must be created before they are retrieved.
    public function add_section(Section $section) {
        $first_letter = $section->get_char();
        $this->dictionary[$first_letter] = $section;
    }

    public function get_section($first_letter) {
        return $this->dictionary[$first_letter];
    }

    public function has_section($first_letter) {
        return isset($this->dictionary[$first_letter]);
    }

    public function get_section_from_char(string $char) {
        // Check to see if this character is a letter.
        $letter_regex = "/[A-z]/";
        $is_letter = preg_match($letter_regex, $char);
        if ($is_letter) {
            return $char;
        }
        else {
            // Check to see if this character is a number.
            $number_regex = "/[0-9]/";
            $is_number = preg_match($number_regex, $char);
            if ($is_number) {
                return "digit";
            }
            return false; // This keyword does not start with a letter nor a number. Return false to signal that this section cannot exist.
        }
    }
}

// This Section (of a Dictionary) is a collection of the website's keywords.
// The purpose of this over a traditional dictionary is better suggestions.
class Section {
    public $section;
    public $char; // Character which all words begin with.

    public function __construct() {
        $this->section = null;
        $this->char = null;
    }

    // All dictionaries must be created before they are retrieved.
    public function create_section($pdo, $site_id, $char) {
        try {
            $this->char = $char;
            $sql = 'SELECT DISTINCT keyword FROM keywords_' . $char . ' WHERE site_id = ? ORDER BY keyword ASC;';
            $statement = $pdo->prepare($sql);
            $statement->execute([$site_id]);
            $this->section = $statement->fetchAll();
            return true;
        } 
        catch (Exception $e) {
            // Our database queries has failed.
            // Print out the error message.
            //$response['dbError'] = $e->getMessage();
            return false;
        }
    }

    public function get_char() {
        return $this->char;
    }

    public function word_at($index) {
        return $this->section[$index]['keyword'];
    }

    public function length() {
        return count($this->section);
    }

    // Input: key is the word we're searching for
    // Output: Array containing a flag stating if an exact match was found, and the index of the match.
    // Perform a binary search for a given term based on a word.
    public function search(string $key) {
        //$arr = parent::get_section();
        $l = 0;
        $h = $this->length();
        $mid = ceil($l + ($h - $l) / 2); 

        while ($h >= $l) {
            // If the element is present at the middle itself 
            if ($this->word_at($mid) === $key) {
                return floor($mid); 
            }
    
            // If element is smaller than mid, then 
            // it can only be present in left subarray 
            if ($this->word_at($mid) > $key) {
                $h = $mid - 1;
            }
            else {
                // Else the element can only be present in right subarray 
                $l = $mid + 1;
            }

            $mid = ceil($l + ($h - $l) / 2);
        }
    
        // We reach here when element is not present in array 
        return false;
    }

    // Input: word (String)
    //        term_index (Integer)
    // Output: Array of Keyword objects
    // Find similar words to the word given.
    public function similar_to(Keyword $keyword) {
        $predictions = [];
        $max_distance = 2; // Maximum allowed levenshtein distance
        foreach ($this->section as $entry) {
            $distance = levenshtein($keyword->get_text(), $entry['keyword']);
            if ($distance <= $max_distance) {
                $good_entry = new Keyword($entry['keyword'], $keyword->get_index());
                $predictions[] = new Prediction($keyword, $good_entry);
            }
        }
        return $predictions;
    }
}

// Keeps track of how relevant each result is.
class ScoreKeeper {
    public $scores;

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
?>