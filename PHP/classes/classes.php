<?php
// This file will be required in all php files outside of the classes and functions folders.
// Exceptions to this include files which use none of the classes in this file.

class Keyword {
    public $text;
    public $index; // Index of the term which this keyword references.
    public $is_misspelled; // Flag for whether the original keyword is misspelled.
    public $has_symbol;
    protected $max; // Maximum dupe_totals of this keyword in the database.

    public function __construct(string $text, int $index) {
        $this->text = $text;
        $this->index = $index;
        $this->max = NULL;
        $this->is_misspelled = false;
    }

    public function get_index() {
        return $this->index;
    }

    public function get_text() {
        return $this->text;
    }

    public function set_text(string $new_keyword) {
        $this->text = $new_keyword;
    }

    public function is_misspelled(bool $bool = null) {
        if ($bool !== null) {
            $this->is_misspelled = $bool;
        }
        return $this->is_misspelled;
    }

    public function has_symbol(bool $bool = null) {
        if ($bool !== null) {
            $this->has_symbol = $bool;
        }
        return $this->has_symbol;
    }

    public function get_max() {
        return $this->max;
    }

    public function set_max(int $new_max) {
        $this->max = $new_max;
    }

    // If the max is set, output a relevance score.
    // If the max is not set, output 0;
    public function relevance(int $dupe_total) {
        if (isset($this->max)) {
            return ceil(($dupe_total / $this->max) * 100);
        }
        else {
            return 0;
        }
    }
}

class Phrase {
    public $keywords; // Array of Keyword objects
    public $text;
    
    public function __construct(array $keywords) {
        $this->keywords = $keywords;
        $this->text = $this->to_string(); // For some reason, this does not work. Use set_text as a temporary solution.
    }

    public function to_string() {
        $phrase = "";
        foreach ($this->keywords as $keyword) {
            $phrase .= $keyword->get_text() . " ";
        }
        return substr($phrase, 0, -1); // Remove the space at the end.
    }
    public function set_phrase(array $keywords) {
        $this->keywords = $keywords;
        $this->text = $this->to_string(); // For some reason, this does not work. Use set_text as a temporary solution.
    }

    function set_text($text) {
        $this->text = $text;
    }

    public function get_keyword(int $index) {
        return $this->keywords[$index];
    }
    public function get_all_keywords() {
        return $this->keywords;
    }
    public function set_keyword($keyword, $index) {
        $this->keywords[$index] = $keyword;
    }

    public function length() {
        return count($this->keywords);
    }

    public function has_misspelling() {
        foreach ($this->keywords as $keyword) {
            if ($keyword->is_misspelled()) {
                return true;
            }
        }
        return false;
    }
}

// A Suggestion is an alternate Phrase
class Suggestion {
    public $original; // Phrase Object
    public $suggestion; // Phrase Object
    public $distance; // Total distance between the two Phrase objects.
    
    public function __construct(Phrase $original, Phrase $suggestion) {
        $this->original = $original;
        $this->suggestion = $suggestion;

        // Calculate the total distance between the two Phrases.
        $total = 0;
        for ($i = 0; $i < $original->length(); $i++) {
            $total += levenshtein($original->get_keyword($i)->get_text(), $suggestion->get_keyword($i)->get_text());
        }
        $this->distance = $total;
    }

    public function get_original_phrase() {
        return $this->original;
        //$phrase = "";
        //foreach ($this->keywords as $keyword) {
        //    $phrase .= $keyword . " ";
        //}
        //return substr($phrase, 0, -1); // Remove the space at the end.*/
    }
    public function get_suggested_phrase() {
        return $this->suggestion;
    }
    public function set_suggested_phrase(Phrase $suggestion) {
        $this->suggestion = $suggestion;
    }
    public function get_total_distance() {
        return $this->distance;
    }
}
?>