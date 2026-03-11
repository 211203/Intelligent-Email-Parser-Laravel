<?php

namespace App\AI\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DetectInputTypeTool implements Tool
{
    public function name(): string
    {
        return 'detect_input_type';
    }

    public function description(): Stringable|string
    {
        return 'Detect whether email content is a booking confirmation or an inquiry using keyword analysis. Returns "booking" or "inquiry". Input: email_content (string, required).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $emailContent = (string) ($request['email_content'] ?? '');
        $type = $this->detect($emailContent);

        return json_encode(['ok' => true, 'type' => $type]);
    }

    /**
     * @return 'booking'|'inquiry'
     */
    private function detect(string $emailContent): string
    {
        $text = strtolower($emailContent);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        $bookingPhrases = [
            'booking confirmation', 'reservation confirmed', 'confirmed reservation',
            'confirmation number', 'booking id', 'reservation id',
            'invoice', 'receipt', 'paid', 'payment received',
            'booking reference', 'booking voucher',
        ];

        $inquiryPhrases = [
            'availability', 'available', 'do you have', 'is there',
            'please let me know', 'could you', 'can you',
            'pricing', 'price', 'rate', 'tariff',
            'quotation', 'quote', 'how much', 'cost',
            'check in', 'check-in', 'looking for', 'interested in',
            'would like to book', 'wish to book', 'planning',
        ];

        $bookingScore = 0;
        foreach ($bookingPhrases as $p) {
            if (str_contains($text, $p)) {
                $bookingScore++;
            }
        }

        $inquiryScore = 0;
        foreach ($inquiryPhrases as $p) {
            if (str_contains($text, $p)) {
                $inquiryScore++;
            }
        }

        if (str_contains($text, 'attach') && $bookingScore >= 1) {
            return 'booking';
        }

        if ($bookingScore > $inquiryScore) {
            return 'booking';
        }

        return 'inquiry';
    }
}
