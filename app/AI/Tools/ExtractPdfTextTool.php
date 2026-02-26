<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\Log;
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

        Log::info('ExtractPdfTextTool.start', [
            'path' => $path,
        ]);

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();

        $result = [
            'ok' => true,
            'text' => $text,
        ];

        Log::info('ExtractPdfTextTool.success', [
            'path' => $path,
            'text_length' => is_string($text) ? mb_strlen($text) : null,
        ]);

        return $result;
    }
}
