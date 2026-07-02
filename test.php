<?php

$customConfigs = [
    'content_style' => '@import url("https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Moul&family=Siemreap&display=swap"); ' .
        'html { background: #f3f4f6; padding: 20px 0; } ' .
        'body { font-family: Calibri, "Battambang", Arial, sans-serif; background: #fff; ' .
        'width: 210mm; min-height: 297mm; ' .
        'padding: 16mm 15mm 16mm 15mm !important; ' .
        'margin: 0 auto !important; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; } ' .
        'p { margin-top: 0; }',
];

$json = json_encode($customConfigs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "JSON: " . $json . "\n\n";

$str_replace = str_replace('"', "'", $json);
echo "REPLACED: " . $str_replace . "\n";
