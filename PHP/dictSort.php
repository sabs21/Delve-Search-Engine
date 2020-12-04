<?php

// Sort that dictionary file A-Z! 

class Word {
    public $word;
    public $metaphone;

    public function _construct($word, $metaphone) {
        $this->word = $word;
        $this->metaphone = $metaphone;
    }
}

$path = "../dictionary.json";

$json = file_get_contents($path);
$data = json_decode($json, TRUE);

$data = (array) $data;

$data = array_keys($data);
sort($data);

$all_words = [];
/*for ($i = 0; $i < count($data); $i++) {
    $all_words[] = new Word($data[$i], metaphone($data[$i]));
}*/

foreach ($data as $word) {
    $all_words[] = new Word($word, metaphone($word));
}

file_put_contents("./results.json", json_encode($all_words));