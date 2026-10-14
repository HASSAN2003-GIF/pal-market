<?php

/*
|--------------------------------------------------------------------------
| Search vocabulary
|--------------------------------------------------------------------------
|
| Each group lists words that mean the same thing to a buyer, in English and
| Kiswahili. A search for any word in a group also finds products named with
| the others ("saruji" finds "Cement"). These are general vocabulary only;
| product-specific names belong in a product's aliases, which administrators
| edit in the catalog. Kiswahili terms should be reviewed by a native speaker
| before launch (see docs/DECISIONS.md).
|
*/

return [
    'synonyms' => [
        // Construction materials
        ['cement', 'saruji', 'simenti', 'sementi'],
        ['rebar', 'nondo', 'steel bar', 'iron bar', 'reinforcement bar', 'reinforcing bar', 'deformed bar'],
        ['roofing sheet', 'bati', 'mabati', 'iron sheet', 'corrugated sheet'],
        ['roofing', 'paa', 'roof'],
        ['wire', 'waya'],
        ['pipe', 'bomba', 'mabomba'],
        ['timber', 'mbao', 'wood', 'lumber'],
        ['sand', 'mchanga'],
        ['aggregate', 'kokoto', 'gravel', 'ballast'],
        ['brick', 'tofali', 'matofali', 'block'],
        ['paint', 'rangi'],
        ['nail', 'msumari', 'misumari'],
        ['tile', 'kigae', 'vigae'],
        ['door', 'mlango', 'milango'],
        ['window', 'dirisha', 'madirisha'],
        ['steel', 'chuma', 'iron'],
        ['glass', 'kioo', 'vioo'],
        ['plumbing', 'mabomba ya maji'],
        ['electrical cable', 'nyaya za umeme', 'cable'],
        // Agricultural produce and inputs
        ['maize', 'mahindi', 'corn'],
        ['maize flour', 'unga wa mahindi', 'sembe', 'dona'],
        ['rice', 'mchele', 'paddy', 'mpunga'],
        ['beans', 'maharage', 'maharagwe'],
        ['fertilizer', 'fertiliser', 'mbolea'],
        ['seed', 'mbegu'],
        ['sugar', 'sukari'],
        ['cooking oil', 'mafuta ya kupikia'],
    ],
];
