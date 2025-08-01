<?php
function pluralize($word) {
    $lastChar = strtolower(substr($word, -1));

    if ($lastChar === 'y') {
        return substr($word, 0, -1) . 'ies';
    } elseif ($lastChar === 's' || $lastChar === 'x') {
        return $word . 'es';
    } else {
        return $word . 's';
    }
}
?>