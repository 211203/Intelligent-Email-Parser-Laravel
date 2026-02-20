<?php

namespace App\AI\Tools;

use RuntimeException;

class ExtractPdfTextTool
{
    /** @return array{ok: bool, text?: string} */
    public function handle(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('PDF file not found');
        }

        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('Smalot PDF Parser is not installed (smalot/pdfparser)');
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();

        return [
            'ok' => true,
            'text' => $text,
        ];
    }
}
