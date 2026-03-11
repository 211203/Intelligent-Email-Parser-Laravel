<?php

namespace App\AI\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Facades\Log;
use Stringable;

class ExtractPdfTextTool implements Tool
{
    public function name(): string
    {
        return 'extract_pdf_text';
    }

    public function description(): Stringable|string
    {
        return 'Extract all text content from a PDF file at the given storage path. Use this after obtaining a pdf_path from fetch_gmail. Input: pdf_path (string, required).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $path = (string) ($request['pdf_path'] ?? '');

        if ($path === '' || !is_file($path)) {
            return json_encode(['ok' => false, 'error' => 'PDF file not found at path: ' . $path]);
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            Log::info('ExtractPdfTextTool.success', ['path' => $path, 'text_length' => strlen($text)]);

            return json_encode(['ok' => true, 'text' => $text]);
        } catch (\Exception $e) {
            Log::error('ExtractPdfTextTool.error', ['path' => $path, 'error' => $e->getMessage()]);
            return json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
