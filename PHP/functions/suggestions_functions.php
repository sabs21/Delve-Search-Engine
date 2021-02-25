<?php
function create_suggestions_from_history(Phrase $phrase, PDO $pdo, int $site_id, int $limit = 10) {
    $suggestions = [];
    // Find all previous searches that contain the currently typed phrase
    $sql = "SELECT search_phrase,COUNT(search_phrase) AS times_searched FROM searches WHERE site_id = ? AND INSTR(search_phrase, ?)>0 AND INSTR(search_phrase, ?)<=" . strlen($phrase->to_string()) . " GROUP BY search_phrase ORDER BY times_searched DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$site_id, $phrase->to_string(), $phrase->to_string()]);
    $suggestions = $statement->fetchAll();
    $suggestions = array_slice($suggestions, 0, $limit); // Ensure that we have at most $limit suggestions
    
    foreach ($suggestions as $i => $suggestion) {
        // String to Phrase conversion
        $terms = explode(' ', $suggestion['search_phrase']);
        $keywords = [];
        foreach ($terms as $j => $term) {
            $keywords[] = new Keyword($term, $j);
        }
        $new_phrase = new Phrase($keywords);
        $suggestions[$i] = new Suggestion($phrase, $new_phrase);
    }
    return $suggestions;
}
?>