<?php

$sourceFile = 'requirements/data/artist_data.csv';
$outputDir = 'requirements/data/chunks';
$chunkSize = 50 * 1024 * 1024; // 50MB

if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$handle = fopen($sourceFile, 'r');
if (!$handle) {
    die("Could not open source file.\n");
}

$header = fgets($handle); // Read header row
$fileIndex = 1;
$currentChunkSize = 0;
$currentHandle = null;

while (($line = fgets($handle)) !== false) {
    if (!$currentHandle) {
        $filename = sprintf('%s/artist_data_part_%03d.csv', $outputDir, $fileIndex);
        $currentHandle = fopen($filename, 'w');
        fwrite($currentHandle, $header);
        $currentChunkSize = strlen($header);
        echo "Created $filename\n";
    }

    fwrite($currentHandle, $line);
    $currentChunkSize += strlen($line);

    if ($currentChunkSize >= $chunkSize) {
        fclose($currentHandle);
        $currentHandle = null;
        $fileIndex++;
    }
}

if ($currentHandle) {
    fclose($currentHandle);
}

fclose($handle);
echo "Splitting complete.\n";

