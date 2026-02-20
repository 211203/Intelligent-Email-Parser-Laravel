<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfExtractionService
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function extractText(string $filePath): string
    {
        if (! is_file($filePath)) {
            throw new RuntimeException("PDF file not found at path: {$filePath}");
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to parse PDF file.', 0, $exception);
        }

        return trim($text ?? '');
    }
}
