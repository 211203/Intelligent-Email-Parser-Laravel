<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailService
{
    /**
     * @return array{access_token: string, expires_in?: int}
     */
    public function refreshAccessToken(): array
    {
        $clientId = (string) env('ZOHO_CLIENT_ID');
        $clientSecret = (string) env('ZOHO_CLIENT_SECRET');
        $refreshToken = (string) env('ZOHO_REFRESH_TOKEN');
        $tokenUrl = rtrim((string) env('ZOHO_TOKEN_URL', 'https://accounts.zoho.com/oauth/v2/token'), '/');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException('Missing Zoho OAuth env vars (ZOHO_CLIENT_ID/ZOHO_CLIENT_SECRET/ZOHO_REFRESH_TOKEN)');
        }

        $resp = Http::asForm()
            ->timeout(30)
            ->post($tokenUrl, [
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $accessToken = $resp['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Zoho token refresh failed: missing access_token');
        }

        return $resp;
    }

    public function fetchLatestEmailPlainText(): string
    {
        $mailFetchUrl = (string) env('MAIL_FETCH_URL');
        if ($mailFetchUrl === '') {
            throw new RuntimeException('Missing MAIL_FETCH_URL');
        }

        $token = $this->refreshAccessToken()['access_token'];

        $resp = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->get($mailFetchUrl)
            ->throw()
            ->json();

        $html = $resp['html'] ?? $resp['body'] ?? $resp['content'] ?? $resp['data']['html'] ?? null;
        $text = $resp['text'] ?? $resp['plain'] ?? $resp['data']['text'] ?? null;

        if (is_string($text) && $text !== '') {
            return $text;
        }

        if (!is_string($html) || $html === '') {
            throw new RuntimeException('MAIL_FETCH_URL response did not include html/text body');
        }

        return $this->stripHtml($html);
    }

    /**
     * Fetch latest email with structured data for FetchGmailTool
     * @return array{body_text?: string, body_html?: string, attachments?: array}
     */
    public function fetchLatestEmail(array $options = []): array
    {
        $mailFetchUrl = (string) env('MAIL_FETCH_URL');
        if ($mailFetchUrl === '') {
            throw new RuntimeException('Missing MAIL_FETCH_URL');
        }

        $token = $this->refreshAccessToken()['access_token'];

        $resp = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->get($mailFetchUrl)
            ->throw()
            ->json();

        $html = $resp['html'] ?? $resp['body'] ?? $resp['content'] ?? $resp['data']['html'] ?? null;
        $text = $resp['text'] ?? $resp['plain'] ?? $resp['data']['text'] ?? null;

        // Extract text content
        $bodyText = '';
        if (is_string($text) && $text !== '') {
            $bodyText = $text;
        } elseif (is_string($html) && $html !== '') {
            $bodyText = $this->stripHtml($html);
        }

        return [
            'body_text' => $bodyText,
            'body_html' => $html,
            'attachments' => $resp['attachments'] ?? [], // For future PDF support
        ];
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
