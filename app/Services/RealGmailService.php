<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GmailService;
use Google\Service\Gmail\Message;

class RealGmailService
{
    private GoogleClient $client;
    private GmailService $gmailService;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->initializeClient();
        $this->gmailService = new GmailService($this->client);
    }

    /**
     * Initialize Google Client with Gmail API credentials
     */
    private function initializeClient(): void
    {
        $clientId = (string) env('GMAIL_CLIENT_ID');
        $clientSecret = (string) env('GMAIL_CLIENT_SECRET');
        $redirectUri = (string) env('GMAIL_REDIRECT_URI');
    
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Missing Gmail API credentials');
        }
    
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        $this->client->addScope(GmailService::GMAIL_READONLY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    
        // 🔥 Proper token setup
        $accessToken = env('GMAIL_ACCESS_TOKEN');
        $refreshToken = env('GMAIL_REFRESH_TOKEN');
    
        if ($accessToken) {
            $token = [
                'access_token' => $accessToken,
            ];
    
            if ($refreshToken) {
                $token['refresh_token'] = $refreshToken;
            }
    
            $this->client->setAccessToken($token);
        }
    }

    /**
     * Get authentication URL for Gmail OAuth
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     */
    public function authenticate(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (!isset($token['access_token'])) {
            throw new RuntimeException('Failed to obtain access token from Gmail');
        }

        // Store the token
        $this->client->setAccessToken($token);

        return $token;
    }

    /**
     * Refresh access token if needed
     */
    public function refreshAccessToken(): array
    {
        if ($this->client->isAccessTokenExpired()) {
            $rt = $this->client->getRefreshToken();
            if (! is_string($rt) || $rt === '') {
                $rt = (string) env('GMAIL_REFRESH_TOKEN');
            }

            if ($rt === '') {
                throw new RuntimeException('Missing Gmail refresh token (GMAIL_REFRESH_TOKEN)');
            }

            $this->client->fetchAccessTokenWithRefreshToken($rt);
        }

        return $this->client->getAccessToken();
    }

    /**
     * Fetch latest unread email
     */
    public function fetchLatestEmail(array $options = []): array
    {
        try {
            // Ensure we have a valid access token
            if ($this->client->isAccessTokenExpired()) {
                $this->refreshAccessToken();
            }

            // Get list of unread messages
            $messages = $this->gmailService->users_messages->listUsersMessages('me', [
                'q' => 'is:unread',
                'maxResults' => 1,
            ]);

            if (empty($messages->getMessages())) {
                throw new RuntimeException('No unread messages found');
            }

            $messageId = $messages->getMessages()[0]->getId();
            $message = $this->gmailService->users_messages->get('me', $messageId, [
                'format' => 'full'
            ]);

            return $this->parseMessage($message);

        } catch (\Exception $e) {
            throw new RuntimeException('Failed to fetch Gmail: ' . $e->getMessage());
        }
    }

    /**
     * Parse Gmail message into structured format
     */
    private function parseMessage(Message $message): array
    {
        $payload = $message->getPayload();
        $headers = $payload->getHeaders();

        // Extract headers
        $subject = '';
        $from = '';
        $date = '';
        
        foreach ($headers as $header) {
            switch (strtolower($header->getName())) {
                case 'subject':
                    $subject = $header->getValue();
                    break;
                case 'from':
                    $from = $header->getValue();
                    break;
                case 'date':
                    $date = $header->getValue();
                    break;
            }
        }

        // Extract body content
        $bodyText = $this->extractBodyText($payload);
        $bodyHtml = $this->extractBodyHtml($payload);

        // Extract attachments
        $attachments = $this->extractAttachments($payload, $message->getId());

        return [
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'subject' => $subject,
            'from' => $from,
            'date' => $date,
            'attachments' => $attachments,
            'message_id' => $message->getId()
        ];
    }

    /**
     * Extract plain text from message payload
     */
    private function extractBodyText($payload): string
    {
        $parts = $payload->getParts() ?? [$payload];

        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/plain') {
                $data = $part->getBody()->getData();
                return base64url_decode($data);
            }
        }

        // Fallback to HTML stripping
        $html = $this->extractBodyHtml($payload);
        return $html ? strip_tags($html) : '';
    }

    /**
     * Extract HTML from message payload
     */
    private function extractBodyHtml($payload): string
    {
        $parts = $payload->getParts() ?? [$payload];

        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/html') {
                $data = $part->getBody()->getData();
                return base64url_decode($data);
            }
        }

        return '';
    }

    /**
     * Extract attachments from message payload
     */
    private function extractAttachments($payload, string $messageId): array
    {
        $attachments = [];
        $parts = $payload->getParts() ?? [];

        foreach ($parts as $part) {
            if ($part->getFilename()) {
                $attachment = [
                    'filename' => $part->getFilename(),
                    'mime_type' => $part->getMimeType(),
                    'size' => $part->getBody()->getSize(),
                    'attachment_id' => $part->getBody()->getAttachmentId()
                ];

                // For PDF attachments, we can download them
                if (str_ends_with(strtolower($part->getFilename()), '.pdf')) {
                    $attachment['type'] = 'pdf';
                    $attachment['download_url'] = $this->getAttachmentUrl($messageId, $part->getBody()->getAttachmentId());
                }

                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * Get attachment download URL
     */
    private function getAttachmentUrl(string $messageId, string $attachmentId): string
    {
        return "https://www.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}";
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): void
    {
        try {
            $this->gmailService->users_messages->modify('me', $messageId, new \Google\Service\Gmail\ModifyMessageRequest([
                'removeLabelIds' => ['UNREAD']
            ]));
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to mark message as read: ' . $e->getMessage());
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(string $messageId, string $attachmentId): string
    {
        try {
            $attachment = $this->gmailService->users_messages_attachments->get('me', $messageId, $attachmentId);
            $data = $attachment->getData();
            
            return base64url_decode($data);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to download attachment: ' . $e->getMessage());
        }
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
