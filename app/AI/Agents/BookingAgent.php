<?php

namespace App\AI\Agents;

use App\AI\Tools\DetectInputTypeTool;
use App\AI\Tools\ExtractPdfTextTool;
use App\AI\Tools\FetchGmailTool;
use App\AI\Tools\ParseBookingDataTool;
use App\AI\Tools\ParseInquiryIntentTool;
use App\AI\Tools\SaveInquiryTool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BookingAgent
{
    /**
     * @var array<string, object>
     */
    private array $tools;

    public function __construct(
        private readonly FetchGmailTool $fetchGmailTool,
        private readonly ExtractPdfTextTool $extractPdfTextTool,
        private readonly ParseBookingDataTool $parseBookingDataTool,
        private readonly DetectInputTypeTool $detectInputTypeTool,
        private readonly ParseInquiryIntentTool $parseInquiryIntentTool,
        private readonly SaveInquiryTool $saveInquiryTool,
    ) {
        $this->tools = [
            'fetch_gmail' => $this->fetchGmailTool,
            'extract_pdf_text' => $this->extractPdfTextTool,
            'detect_input_type' => $this->detectInputTypeTool,
            'parse_booking_data' => $this->parseBookingDataTool,
            'parse_inquiry_intent' => $this->parseInquiryIntentTool,
            'save_inquiry' => $this->saveInquiryTool,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?string $pdfPath, string $clientName): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    "You are a strict booking automation agent that handles both booking confirmations and inquiries.",
                    "",
                    "You have access to the following tools:",
                    "",
                    "1) fetch_gmail",
                    "   Input: { client_name: string }",
                    "   Output: { ok: bool, pdf_path: string }",
                    "",
                    "2) extract_pdf_text",
                    "   Input: { pdf_path: string }",
                    "   Output: { ok: bool, text: string }",
                    "",
                    "3) detect_input_type",
                    "   Input: { email_content: string }",
                    "   Output: { ok: bool, type: \"booking\" | \"inquiry\" }",
                    "",
                    "4) parse_booking_data",
                    "   Input: { text: string, client_name?: string }",
                    "   Output: { structured booking JSON }",
                    "",
                    "5) parse_inquiry_intent",
                    "   Input: { text: string, client_name?: string }",
                    "   Output: { structured inquiry JSON }",
                    "",
                    "6) save_inquiry",
                    "   Input: { inquiry_data: object, source_type: string, client_name: string, raw_content?: string }",
                    "   Output: { ok: bool, inquiry_id?: int, message?: string }",
                    "",
                    "Rules:",
                    "- If pdf_path is null, you MUST first call fetch_gmail.",
                    "- After you have a valid pdf_path, you MUST call extract_pdf_text.",
                    "- After you receive text, you MUST call detect_input_type.",
                    "- If type = \"booking\": call parse_booking_data then return booking JSON.",
                    "- If type = \"inquiry\": call parse_inquiry_intent then save_inquiry then return inquiry JSON with inquiry_id.",
                    "- Never skip steps.",
                    "- Never invent a pdf_path.",
                    "- Never modify tool output.",
                    "- Your FINAL response MUST be ONLY the JSON returned from parse_booking_data OR parse_inquiry_intent (with inquiry_id).",
                    "- Do NOT add explanations, markdown, or text outside JSON.",
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'client_name' => $clientName,
                    'pdf_path' => $pdfPath,
                ], JSON_THROW_ON_ERROR),
            ],
        ];
    
        $tools = $this->toolSchemas();
        $model = env('GROQ_MODEL', 'llama-3.3-70b-versatile');
        $maxIterations = 6;
    
        for ($i = 0; $i < $maxIterations; $i++) {
    
            $response = $this->groqChat($model, $messages, $tools);
    
            $choice = $response['choices'][0]['message'] ?? null;
            if (! is_array($choice)) {
                throw new RuntimeException('AI response missing message payload');
            }
    
            $messages[] = [
                'role' => $choice['role'] ?? 'assistant',
                'content' => $choice['content'] ?? '',
                'tool_calls' => $choice['tool_calls'] ?? null,
            ];
    
            $toolCalls = $choice['tool_calls'] ?? null;
    
            // ✅ If no tool calls → final response expected
            if (empty($toolCalls)) {
    
                $content = trim((string) ($choice['content'] ?? ''));
    
                if ($content === '') {
                    throw new RuntimeException('Final AI response is empty');
                }
    
                $decoded = json_decode($content, true);
    
                if (! is_array($decoded)) {
                    Log::error('Final AI response not valid JSON', [
                        'content' => $content
                    ]);
                    throw new RuntimeException('AI final response is not valid JSON');
                }
    
                return $decoded;
            }
    
            foreach ($toolCalls as $toolCall) {
    
                $function = $toolCall['function'] ?? [];
                $name = $function['name'] ?? null;
                $argumentsJson = $function['arguments'] ?? '{}';
    
                if (! is_string($name) || $name === '') {
                    throw new RuntimeException('Tool call missing function name');
                }
    
                if (! isset($this->tools[$name])) {
                    throw new RuntimeException("Unknown tool requested: {$name}");
                }
    
                $args = json_decode((string) $argumentsJson, true);
                if (! is_array($args)) {
                    $args = [];
                }
    
                // 🔐 Strict input validation
                if ($name === 'extract_pdf_text' && empty($args['pdf_path'])) {
                    throw new RuntimeException('extract_pdf_text called without pdf_path');
                }

                if ($name === 'detect_input_type' && empty($args['email_content'])) {
                    throw new RuntimeException('detect_input_type called without email_content');
                }

                if ($name === 'parse_booking_data' && empty($args['text'])) {
                    throw new RuntimeException('parse_booking_data called without text');
                }

                if ($name === 'parse_inquiry_intent' && empty($args['text'])) {
                    throw new RuntimeException('parse_inquiry_intent called without text');
                }

                if ($name === 'save_inquiry' && empty($args['inquiry_data'])) {
                    throw new RuntimeException('save_inquiry called without inquiry_data');
                }
    
                Log::info('BookingAgent.tool_call', [
                    'tool' => $name,
                    'args' => $args,
                ]);
    
                $result = match ($name) {
                    'fetch_gmail' =>
                        $this->fetchGmailTool->handle(
                            isset($args['client_name'])
                                ? (string) $args['client_name']
                                : $clientName
                        ),

                    'extract_pdf_text' =>
                        $this->extractPdfTextTool->handle(
                            (string) $args['pdf_path']
                        ),

                    'detect_input_type' =>
                        $this->detectInputTypeTool->handle(
                            (string) $args['email_content']
                        ),

                    'parse_booking_data' =>
                        $this->parseBookingDataTool->handle(
                            (string) $args['text'],
                            isset($args['client_name'])
                                ? (string) $args['client_name']
                                : $clientName
                        ),

                    'parse_inquiry_intent' =>
                        $this->parseInquiryIntentTool->handle(
                            (string) $args['text'],
                            isset($args['client_name'])
                                ? (string) $args['client_name']
                                : $clientName
                        ),

                    'save_inquiry' =>
                        $this->saveInquiryTool->handle(
                            $args['inquiry_data'],
                            isset($args['source_type'])
                                ? (string) $args['source_type']
                                : 'email',
                            isset($args['client_name'])
                                ? (string) $args['client_name']
                                : $clientName,
                            $args['raw_content'] ?? null
                        ),

                    default =>
                        throw new RuntimeException("Tool handler not implemented: {$name}")
                };
    
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'] ?? '',
                    'content' => json_encode($result),
                ];
    
                Log::info('BookingAgent.tool_result', [
                    'tool' => $name,
                    'result_keys' => array_keys($result),
                ]);
            }
        }
    
        throw new RuntimeException('Tool-calling loop exceeded maximum iterations');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolSchemas(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_gmail',
                    'description' => 'Fetch latest unread Gmail and return PDF path',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'client_name' => ['type' => 'string'],
                        ],
                        'required' => ['client_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'extract_pdf_text',
                    'description' => 'Extract text from PDF path',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pdf_path' => ['type' => 'string'],
                        ],
                        'required' => ['pdf_path'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'detect_input_type',
                    'description' => 'Detect if email content is booking or inquiry',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'email_content' => ['type' => 'string'],
                        ],
                        'required' => ['email_content'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'parse_booking_data',
                    'description' => 'Parse booking data from extracted text',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'client_name' => ['type' => 'string'],
                        ],
                        'required' => ['text'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'parse_inquiry_intent',
                    'description' => 'Parse inquiry intent and details from text',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'client_name' => ['type' => 'string'],
                        ],
                        'required' => ['text'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'save_inquiry',
                    'description' => 'Save inquiry data to database',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'inquiry_data' => ['type' => 'object'],
                            'source_type' => ['type' => 'string'],
                            'client_name' => ['type' => 'string'],
                            'raw_content' => ['type' => 'string'],
                        ],
                        'required' => ['inquiry_data', 'source_type', 'client_name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<string, mixed>
     */
    private function groqChat(string $model, array $messages, array $tools): array
    {
        $resp = $this->groqHttp()
            ->post('/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'tools' => $tools,
            ])
            ->throw()
            ->json();

        if (! is_array($resp)) {
            throw new RuntimeException('Groq API did not return an array response');
        }

        /** @var array<string, mixed> $resp */
        return $resp;
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
