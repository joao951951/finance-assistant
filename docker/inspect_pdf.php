<?php

use Smalot\PdfParser\Parser;

require __DIR__.'/../vendor/autoload.php';

$parser = new Parser;
$pdf = $parser->parseFile(__DIR__.'/BradescoCartoes18-03-2026-21-26-52.pdf');
$text = $pdf->getText();

echo '=== TOTAL CHARS: '.strlen($text).' | LINES: '.substr_count($text, "\n")." ===\n\n";

$lines = explode("\n", $text);
foreach ($lines as $i => $line) {
    $line = trim($line);
    if (mb_strlen($line) < 3) {
        continue;
    }
    printf("[%03d] %s\n", $i + 1, $line);
}
