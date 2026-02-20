<?php

namespace App\AI\Agents;

use App\AI\Tools\ExtractPdfTextTool;
use App\AI\Tools\FetchGmailTool;
use App\AI\Tools\ParseBookingDataTool;
use App\AI\Tools\SaveBookingTool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BookingAgent
{
    /** @var array<string, object> */
    private array $tools;

    public function __construct(
        private readonly FetchGmailTool $fetchLatestEmailTool,
        private readonly ExtractPdfTextTool $extractPdfTextTool,
        private readonly ParseBookingDataTool $parseBookingDataTool,
        private readonly SaveBookingTool $saveBookingTool,
    ) {
        $this->tools = [
            'fetch_latest_email' => $this->fetchLatestEmailTool,
            'extract_pdf_text' => $this->extractPdfTextTool,
            'parse_booking_data' => $this->parseBookingDataTool,
            'save_booking' => $this->saveBookingTool,
        ];
    }

    /**
     * @param array{
     *   pdf_path?: string|null,
     *   client_name?: string|null
     * } $context
     * @return array<string, mixed>
     */
    public function run(array $context): array
    {
        $pdfPath = Arr::get($context, 'pdf_path');
        $clientName = Arr::get($context, 'client_name');

        $messages = [
            [
                'role' => 'system',
                'content' => "You are a tool-using agent that processes booking emails or PDFs and returns strictly valid JSON. You must use tools to do work. Return final result as JSON with keys: booking, saved. booking is structured booking data. saved is save confirmation.",
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'instruction' => 'Process the latest booking content into structured JSON and save it.',
                    'context' => [
                        'has_pdf' => (bool) $pdfPath,
                        'pdf_path' => $pdfPath,
                        'client_name' => $clientName,
                    ],
                    'required_flow' => [
                        'If has_pdf then call extract_pdf_text else call fetch_latest_email',
                        'Then call parse_booking_data',
                        'Then call save_booking',
                        'Then provide final JSON response',
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        $toolsSchema = $this->toolsSchema();

        $pendingToolResult = null;
        $maxSteps = 8;

        for ($i = 0; $i < $maxSteps; $i++) {
            $payload = [
                'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                'messages' => $messages,
                'temperature' => 0,
                'tools' => $toolsSchema,
                'tool_choice' => 'auto',
            ];

            $response = $this->groqHttp()
                ->post('/openai/v1/chat/completions', $payload)
                ->throw()
                ->json();

            $choice = $response['choices'][0] ?? null;
            if (!$choice) {
                throw new RuntimeException('Groq: missing choices');
            }

            $message = $choice['message'] ?? [];
            $toolCalls = $message['tool_calls'] ?? [];

            if (!empty($toolCalls)) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $call) {
                    $toolName = $call['function']['name'] ?? null;
                    $toolArgsJson = $call['function']['arguments'] ?? '{}';
                    $toolCallId = $call['id'] ?? null;

                    if (!$toolName || !$toolCallId) {
                        throw new RuntimeException('Groq: invalid tool call format');
                    }

                    $toolArgs = json_decode($toolArgsJson, true);
                    if (!is_array($toolArgs)) {
                        $toolArgs = [];
                    }

                    $result = $this->executeTool($toolName, $toolArgs);
                    $pendingToolResult = $result;

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ];
                }

                continue;
            }

            $content = $message['content'] ?? '';
            $final = json_decode($content, true);
            if (is_array($final)) {
                return $final;
            }

            if (is_array($pendingToolResult)) {
                return $pendingToolResult;
            }

            throw new RuntimeException('Groq: expected final JSON response from agent');
        }

        throw new RuntimeException('Agent exceeded max tool-calling steps');
    }

    /** @return array<int, array<string, mixed>> */
    private function toolsSchema(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_latest_email',
                    'description' => 'Fetch latest Zoho email and return plain text content.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'extract_pdf_text',
                    'description' => 'Extract raw text from a PDF file at a given path.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => ['type' => 'string'],
                        ],
                        'required' => ['path'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'parse_booking_data',
                    'description' => 'Parse booking data from raw text and optional client_name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'client_name' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['text'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'save_booking',
                    'description' => 'Persist a booking record into database.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'booking' => ['type' => 'object'],
                        ],
                        'required' => ['booking'],
                    ],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $args */
    private function executeTool(string $name, array $args): array
    {
        if (!array_key_exists($name, $this->tools)) {
            throw new RuntimeException("Unknown tool: {$name}");
        }

        try {
            return match ($name) {
                'fetch_latest_email' => $this->fetchLatestEmailTool->handle(),
                'extract_pdf_text' => $this->extractPdfTextTool->handle((string) ($args['path'] ?? '')),
                'parse_booking_data' => $this->parseBookingDataTool->handle(
                    (string) ($args['text'] ?? ''),
                    Arr::get($args, 'client_name')
                ),
                'save_booking' => $this->saveBookingTool->handle((array) ($args['booking'] ?? [])),
                default => throw new RuntimeException("Unknown tool: {$name}"),
            };
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function groqHttp(): PendingRequest
    {
        $baseUrl = rtrim((string) env('GROQ_BASE_URL', 'https://api.groq.com'), '/');
        $apiKey = (string) env('GROQ_API_KEY');

        if ($apiKey === '') {
            throw new RuntimeException('Missing GROQ_API_KEY');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(60);
    }
}
