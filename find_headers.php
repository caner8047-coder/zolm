<?php
$logs = file_get_contents(__DIR__ . '/storage/logs/laravel.log');
preg_match_all("/EXCEL RAW HEADERS DUMP.*?(?=\[202[0-9]-)/s", $logs, $matches);
if (isset($matches[0]) && !empty($matches[0])) {
    echo end($matches[0]);
} else {
    echo "NO DUMP FOUND";
}
