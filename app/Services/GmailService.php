<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GmailService
{
    private const GMAIL_API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const DEFAULT_QUERY = 'is:unread';

    private const ATTACHMENT_DISK = 'local';

    private const ATTACHMENT_DIR = 'gmail-attachments';

    /**
     * @return array{access_token: string, expires_in?: int}
     */
    public function refreshAccessToken(): array
    {
        $clientId = (string) config('services.gmail.client_id', env('GMAIL_CLIENT_ID'));
        $clientSecret = (string) config('services.gmail.client_secret', env('GMAIL_CLIENT_SECRET'));
        $refreshToken = (string) config('services.gmail.refresh_token', env('GMAIL_REFRESH_TOKEN'));

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException(
                'Missing Gmail OAuth env (GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN)'
            );
        }

        $resp = Http::asForm()
            ->timeout(30)
            ->post(self::OAUTH_TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $accessToken = $resp['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Gmail token refresh failed: missing access_token');
        }

        return $resp;
    }

    /**
     * Fetch latest email (by default latest unread) and extract body + attachments.
     *
     * @param array{query?: string, pdf_only?: bool} $options
     *   - query: Gmail search query (default: 'is:unread')
     *   - pdf_only: if true, only include application/pdf attachments (default: false)
     * @return array{
     *     id: string,
     *     threadId: string,
     *     subject: string,
     *     from: string,
     *     date: string,
     *     snippet: string,
     *     plain: string,
     *     html: string,
     *     attachments: array<int, array{filename: string, mimeType: string, path: string, full_path: string}>
     * }
     */
    public function fetchLatestEmail(array $options = []): array
    {
        $query = $options['query'] ?? self::DEFAULT_QUERY;
        $pdfOnly = (bool) ($options['pdf_only'] ?? false);

        Log::info('GmailService.fetchLatestEmail.start', [
            'query' => $query,
            'pdf_only' => $pdfOnly,
        ]);

        $token = $this->refreshAccessToken()['access_token'];

        $listResp = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get(self::GMAIL_API_BASE . '/messages', [
                'maxResults' => 1,
                'q' => $query,
            ])
            ->throw()
            ->json();

        $messages = $listResp['messages'] ?? [];
        if (empty($messages)) {
            Log::warning('GmailService.fetchLatestEmail.no_messages', [
                'query' => $query,
            ]);
            throw new RuntimeException('No messages found for query: ' . $query);
        }

        $messageId = $messages[0]['id'];
        $fullMessage = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get(self::GMAIL_API_BASE . '/messages/' . $messageId, ['format' => 'full'])
            ->throw()
            ->json();

        $parsed = $this->parseMessage($token, $messageId, $fullMessage, $pdfOnly);

        Log::info('GmailService.fetchLatestEmail.success', [
            'message_id' => $messageId,
            'attachments_count' => \count($parsed['attachments'] ?? []),
        ]);

        return $parsed;
    }

    /**
     * Fetch latest email and return plain text only (for tools that expect body text).
     *
     * @param array{query?: string, pdf_only?: bool} $options
     */
    public function fetchLatestEmailPlainText(array $options = []): string
    {
        $email = $this->fetchLatestEmail($options);
        $plain = $email['plain'] ?? '';
        if ($plain !== '') {
            return $plain;
        }
        $html = $email['html'] ?? '';
        if ($html !== '') {
            return $this->stripHtml($html);
        }
        return $email['snippet'] ?? '';
    }

    /**
     * @param array<string, mixed> $fullMessage Gmail API message resource
     * @param array{
     *     id: string,
     *     threadId: string,
     *     subject: string,
     *     from: string,
     *     date: string,
     *     snippet: string,
     *     plain: string,
     *     html: string,
     *     attachments: array<int, array{filename: string, mimeType: string, path: string, full_path: string}>
     * }
     */
    private function parseMessage(string $accessToken, string $messageId, array $fullMessage, bool $pdfOnly): array
    {
        $payload = $fullMessage['payload'] ?? [];
        $headers = $this->indexHeaders($payload['headers'] ?? []);
        $snippet = (string) ($fullMessage['snippet'] ?? '');

        $result = [
            'id' => $messageId,
            'threadId' => (string) ($fullMessage['threadId'] ?? ''),
            'subject' => $headers['Subject'] ?? '',
            'from' => $headers['From'] ?? '',
            'date' => $headers['Date'] ?? '',
            'snippet' => $snippet,
            'plain' => '',
            'html' => '',
            'attachments' => [],
        ];

        $parts = $payload['parts'] ?? [];
        if (empty($parts)) {
            $body = $payload['body'] ?? [];
            $result['plain'] = $this->decodeBodyData($body);
            if ($result['plain'] === '' && $snippet !== '') {
                $result['plain'] = $snippet;
            }
            return $result;
        }

        $this->parsePartsRecursive($accessToken, $messageId, $parts, $result, $pdfOnly);

        if ($result['plain'] === '' && $result['html'] !== '') {
            $result['plain'] = $this->stripHtml($result['html']);
        }
        if ($result['plain'] === '' && $snippet !== '') {
            $result['plain'] = $snippet;
        }

        return $result;
    }

    /**
     * Recursively traverse MIME parts (handles multipart/mixed, multipart/alternative, etc.).
     *
     * @param array<int, array<string, mixed>> $parts
     * @param array{plain: string, html: string, attachments: array} $result
     */
    private function parsePartsRecursive(
        string $accessToken,
        string $messageId,
        array $parts,
        array &$result,
        bool $pdfOnly
    ): void {
        foreach ($parts as $part) {
            $nestedParts = $part['parts'] ?? [];
            if (! empty($nestedParts)) {
                $this->parsePartsRecursive($accessToken, $messageId, $nestedParts, $result, $pdfOnly);
                continue;
            }

            $mimeType = (string) ($part['mimeType'] ?? '');
            $filename = $this->getPartHeader($part, 'filename');
            $body = $part['body'] ?? [];
            $attachmentId = $body['attachmentId'] ?? null;

            if ($attachmentId !== null || $filename !== '') {
                if ($pdfOnly && strtolower($mimeType) !== 'application/pdf') {
                    continue;
                }
                $content = $attachmentId
                    ? $this->fetchAttachment($accessToken, $messageId, $attachmentId)
                    : $this->decodeBodyData($body);
                $path = $this->storeAttachment($messageId, $filename ?: 'attachment', $mimeType, $content);
                $result['attachments'][] = [
                    'filename' => $filename ?: 'attachment',
                    'mimeType' => $mimeType ?: 'application/octet-stream',
                    'path' => $path,
                    'full_path' => Storage::disk(self::ATTACHMENT_DISK)->path($path),
                ];
                continue;
            }

            $decoded = $this->decodeBodyData($body);
            if ($decoded === '') {
                continue;
            }
            if (str_starts_with(strtolower($mimeType), 'text/html') && $result['html'] === '') {
                $result['html'] = $decoded;
            } elseif (str_starts_with(strtolower($mimeType), 'text/plain') && $result['plain'] === '') {
                $result['plain'] = $decoded;
            }
        }
    }

    /**
     * Store attachment to disk and return relative path (no raw content in memory for caller).
     */
    private function storeAttachment(string $messageId, string $filename, string $mimeType, string $content): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME) ?: 'attachment';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base);
        $unique = $safeName . '-' . substr(md5($filename . microtime()), 0, 8) . ($ext ? '.' . $ext : '');
        $dir = self::ATTACHMENT_DIR . '/' . $messageId;
        $path = $dir . '/' . $unique;

        Storage::disk(self::ATTACHMENT_DISK)->put($path, $content);

        Log::info('GmailService.storeAttachment', [
            'message_id' => $messageId,
            'path' => $path,
            'mimeType' => $mimeType,
        ]);

        return $path;
    }

    /**
     * @param array<int, array{name: string, value: string}> $headers
     * @return array<string, string>
     */
    private function indexHeaders(array $headers): array
    {
        $indexed = [];
        foreach ($headers as $h) {
            $name = $h['name'] ?? '';
            $value = $h['value'] ?? '';
            if ($name !== '') {
                $indexed[$name] = $value;
            }
        }
        return $indexed;
    }

    /**
     * @param array<string, mixed> $part
     */
    private function getPartHeader(array $part, string $name): string
    {
        foreach ($part['headers'] ?? [] as $h) {
            if (strcasecmp($h['name'] ?? '', $name) === 0) {
                return (string) ($h['value'] ?? '');
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function decodeBodyData(array $body): string
    {
        $data = $body['data'] ?? null;
        if (! is_string($data) || $data === '') {
            return '';
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : '';
    }

    private function fetchAttachment(string $accessToken, string $messageId, string $attachmentId): string
    {
        $url = self::GMAIL_API_BASE . '/messages/' . $messageId . '/attachments/' . $attachmentId;
        $resp = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get($url)
            ->throw()
            ->json();
        $data = $resp['data'] ?? null;
        if (! is_string($data)) {
            return '';
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : '';
    }

    private function stripHtml(string $html): string
    {
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*\/p\s*>/i', "\n", $html) ?? $html;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $text = $dom->textContent ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
