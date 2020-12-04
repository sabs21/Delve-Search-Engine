<?php

// Sort that dictionary file A-Z! 

class Word {
    public $word;
    public $metaphone;

    public function __construct($word) {
        $this->word = $word;
        $this->metaphone =  metaphone($word);
    }

    public function get_word() {
        return $this->$word;
    }

    public function get_metaphone() {
        return $this->$metaphone;
    }
}

$path = "../dictionary.json";

$json = file_get_contents($path);
$data = json_decode($json, TRUE);

$data = (array) $data;
$data = array_keys($data);
sort($data);

$all_words = [];
foreach ($data as $word) {
    $all_words[] = new Word($word);
}

//$metaphoneSorted = $all_words;

//usort($metaphoneSorted, 'cmp_obj');

file_put_contents("./wordSorted2.json", json_encode($all_words));

function cmp_obj($a, $b) {
    $al = strtolower($a->metaphone);
    $bl = strtolower($b->metaphone);
    if ($al == $bl) {
        return 0;
    }
    return ($al > $bl) ? +1 : -1;
}