<?php

// Sort that dictionary file A-Z! 

class Word {
    public $word;
    public $meanings;
    public $metaphone;
    public $synonyms;
    public $pos;

    public function __construct($word, $meanings, $synonyms) {
        $this->word = strtolower($word);
        $this->metaphone = metaphone($word);
        $this->meanings = $meanings;
        $this->synonyms = $synonyms;
        $this->pos = NULL;
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

    public function get_synonyms() {
        return $this->synonyms;
    }
}

$wholeDict = [];

// Concatenate all dictionary files together.
for ($i = 0; $i < 26; $i++) {
    $current_letter = chr(65 + $i);
    $path = './dictionary/D' . $current_letter . '.json';
    $json = file_get_contents($path);
    $data = json_decode($json, TRUE);
    $words = array_keys($data);

    foreach ($words as $word) {
        $entry = new Word($word, $data[$word]['MEANINGS'], $data[$word]['SYNONYMS']);
        $definitions = []; // Grab each definition
        foreach($entry->get_meanings() as $definition) {
            $definitions[] = $definition;
        }
        $entry->set_meanings($definitions);
        $wholeDict[] = $entry;
    }
}

//$metaphoneSorted = $all_words;

usort($wholeDict, 'nat_cmp');

//file_put_contents("./metaphoneSorted.json", json_encode($wholeDict));
file_put_contents("metaphoneSorted2.json", json_encode($wholeDict));

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
    //$al = strtolower($a->metaphone);
    //$bl = strtolower($b->metaphone);
    /*(if ($al == $bl) {
        return 0;
    }
    return ($al > $bl) ? +1 : -1;*/
    return strnatcasecmp($a->metaphone, $b->metaphone);
}
