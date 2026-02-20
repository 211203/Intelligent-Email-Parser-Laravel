<?php

namespace App\Helpers;

class EmailPreprocessor
{
    public function preprocess(string $text): string
    {
        $lines = preg_split("/\R/", $text) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $tagged = $this->tagFinancialLine($line);
            $out[] = $tagged;
        }

        return trim(implode("\n", $out));
    }

    private function tagFinancialLine(string $line): string
    {
        $normalized = trim($line);

        if (preg_match('/\b(daily|per\s*day)\b/i', $normalized) && preg_match('/\b\d+(?:\.\d+)?\b/', $normalized)) {
            return "[FINANCIAL_LINE] {$normalized}";
        }

        if (preg_match('/\b(total|amount|paid|price|rate)\b/i', $normalized) && preg_match('/\b\d+(?:\.\d+)?\b/', $normalized)) {
            return "[FINANCIAL_LINE] {$normalized}";
        }

        return $normalized;
    }
}
