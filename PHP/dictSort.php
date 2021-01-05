<?php

// Sort that dictionary file A-Z! 

class Word {
    public $word;
    public $meanings;
    public $metaphone;
    //public $synonyms;
    //public $pos;

    //public function __construct($word, $meanings, $synonyms) old
    public function __construct($word, $meanings) {
        $this->word = strtolower($word);
        $this->metaphone = metaphone($word);
        $this->meanings = $meanings;
        //$this->synonyms = $synonyms;
        //$this->pos = NULL;
    }

    public function get_word() {
        return $this->$word;
    }

    public function get_metaphone() {
        return $this->$metaphone;
    }

    /*public function extract_pos() {
        foreach($this->meanings as $definition) {
            $this->pos[] = $this->meanings[$definition][0];
        }
    }*/

    public function get_meanings() {
        return $this->meanings;
    }

    public function set_meanings($meanings) {
        $this->meanings = $meanings;
    }

    /*public function get_synonyms() {
        return $this->synonyms;
    }*/
}

//$wholeDict = [];

// Concatenate all dictionary files together.
for ($i = 0; $i < 26; $i++) {
    $dict_piece = [];

    // Get the [letter] section of the dictionary and load it.
    $current_letter = chr(65 + $i);
    $path = './dictionary/M' . $current_letter . '.json';
    $json = file_get_contents($path);
    $data = json_decode($json, TRUE);
    $words = array_keys($data);

    // Format each word
    foreach ($words as $word) {
        //$entry = new Word($word, $data[$word]['MEANINGS'], $data[$word]['SYNONYMS']);
        $entry = new Word($word, $data[$word]['MEANINGS']);
        $definitions = []; // Grab each definition
        foreach($entry->get_meanings() as $definition) {
            if (!empty($definition)) {
                $definitions[] = $definition;
            }
        }
        $entry->set_meanings($definitions);
        $dict_piece[] = $entry;
    }

    usort($dict_piece, 'nat_cmp');
    file_put_contents("./dictionary/M".$current_letter.".json", json_encode($dict_piece));
}

//$metaphoneSorted = $all_words;

//usort($wholeDict, 'nat_cmp');

//file_put_contents("./metaphoneSorted.json", json_encode($wholeDict));
//file_put_contents("metaphoneSorted2.json", json_encode($wholeDict));

/*$path = "./wordSorted.json";

$json = file_get_contents($path);
$wordSort = json_decode($json, TRUE);

$path = "./wordSorted.json";

$json = file_get_contents($path);
$wordSort = json_decode($json, TRUE);

$data = (array) $data;
$data = array_keys($data);
sort($data);

$all_words = [];
foreach ($data as $word) {
    $all_words[] = new Word($word);
}


file_put_contents("./wordSorted2.json", json_encode($all_words));*/

/*$path = "./wordSorted.json";

$json = file_get_contents($path);
$data = json_decode($json, TRUE);

$data = (array) $data;
$data = array_keys($data);
sort($data);

$all_words = [];
foreach ($data as $word) {
    $all_words[] = new Word($word);
}

$metaphoneSorted = $all_words;

usort($metaphoneSorted, 'cmp_obj');

file_put_contents("./metaphoneSorted.json", json_encode($all_words));*/

function nat_cmp($a, $b) {
    //$al = strtolower($a->get_word());
    //$bl = strtolower($b->get_word());
    /*echo $a->get_word();
    if ($a == $b) {
        return 0;
    }
    return ($a > $b) ? +1 : -1;*/
    return strnatcasecmp($a->metaphone, $b->metaphone);
}
