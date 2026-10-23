<?php

function patchFile(string $file, string $search, string $replace): bool {
    $content = file_get_contents($file);
    if ($content === false) {
        echo "Error reading $file\n";
        return false;
    }
    // Normalize newlines in both for matching, or direct str_replace
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replace, $content);
        file_put_contents($file, $content);
        echo "Patched $file directly\n";
        return true;
    }
    // Try normalizing \r\n to \n
    $normContent = str_replace("\r\n", "\n", $content);
    $normSearch = str_replace("\r\n", "\n", $search);
    $normReplace = str_replace("\r\n", "\n", $replace);
    if (strpos($normContent, $normSearch) !== false) {
        $normContent = str_replace($normSearch, $normReplace, $normContent);
        // Save back with original \r\n if needed, or \r\n everywhere
        $content = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $normContent));
        file_put_contents($file, $content);
        echo "Patched $file with normalized newlines\n";
        return true;
    }
    echo "Could not find search string in $file\n";
    return false;
}
