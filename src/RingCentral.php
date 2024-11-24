<?php

namespace SheavesCapital\RingCentral;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SheavesCapital\RingCentral\Exceptions\CouldNotSendMessage;

class RingCentral {
    const ACCESS_TOKEN_TTL = 3600; // 60 minutes
    const REFRESH_TOKEN_TTL = 604800; // 1 week
    const TOKEN_ENDPOINT = '/restapi/oauth/token';
    const API_VERSION = 'v1.0';
    const URL_PREFIX = '/restapi';
    protected string $serverUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $jwt;
    protected string $loggedInExtension;
    protected string $loggedInExtensionId;

    public function setClientId(string $clientId): static {
        $this->clientId = $clientId;

        return $this;
    }

    public function setClientSecret(string $clientSecret): static {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function setServerUrl(string $serverUrl): static {
        $this->serverUrl = $serverUrl;

        return $this;
    }

    public function setjWT(string $jwt): static {
        $this->jwt = $jwt;

        return $this;
    }

    protected static function errorHandler(Response $response): void {
        throw new \Exception($response->json('message'), $response->status());
    }

    protected function login(): Response {
        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->serverUrl.self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt,
                'access_token_ttl' => self::ACCESS_TOKEN_TTL,
                'refresh_token_ttl' => self::REFRESH_TOKEN_TTL,
            ]);
        $response->onError($this->errorHandler(...));
        $access_token = $response->json('access_token');
        Cache::put('ringcentral_access_token', $access_token, $response->json('expires_in'));
        Cache::put('ringcentral_refresh_token', $response->json('refresh_token'), $response->json('refresh_token_expires_in'));
        return $access_token;
    }

    protected function refresh(): string {
        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->serverUrl.self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => Cache::get('ringcentral_refresh_token'),
                'access_token_ttl' => self::ACCESS_TOKEN_TTL,
                'refresh_token_ttl' => self::REFRESH_TOKEN_TTL,
            ]);
        $response->onError($this->errorHandler(...));
        $access_token = $response->json('access_token');
        Cache::put('ringcentral_access_token', $access_token, $response->json('expires_in'));
        return $access_token;
    }

    protected function accessToken(): string {
        if (Cache::has('ringcentral_access_token')) {
            return Cache::get('ringcentral_access_token');
        } elseif (Cache::has('ringcentral_refresh_token')) {
            return $this->refresh();
        } else {
            return $this->login();
        }
    }

    protected function prependPath(string $url): string {
        return $this->serverUrl.self::URL_PREFIX.'/'.self::API_VERSION.$url;
    }

    public function get(string $url, array $query = [], array $headers = [], bool $prependPath = true): Response {
        $response = Http::withToken($this->accessToken())
            ->withHeaders($headers)
            ->get(
                $prependPath ? $this->prependPath($url) : $url,
                $query
            );
        $response->onError($this->errorHandler(...));
        return $response;
    }

    public function post(string $url, array $body = [], array $headers = [], bool $prependPath = true): Response {
        $response = Http::withToken($this->accessToken())
            ->withHeaders($headers)
            ->post(
                $prependPath ? $this->prependPath($url) : $url,
                $body
            );
        $response->onError($this->errorHandler(...));
        return $response;
    }

    public function delete(string $url, array $query = [], array $headers = [], bool $prependPath = true): Response {
        $response = Http::withToken($this->accessToken())
            ->withHeaders($headers)
            ->delete(
                $prependPath ? $this->prependPath($url) : $url,
                $query
            );
        $response->onError($this->errorHandler(...));
        return $response;
    }

    public function setLoggedInExtension(): void {
        $extension = $this->get('/account/~/extension/~/')->json();
        $this->loggedInExtensionId = $extension->id;
        $this->loggedInExtension = $extension->extensionNumber;
    }

    public function loggedInExtensionId(): string {
        return $this->loggedInExtensionId;
    }

    public function loggedInExtension(): string {
        return $this->loggedInExtension;
    }

    public function sendMessage(array $message): Response {
        if (empty($message['from'])) {
            throw CouldNotSendMessage::toNumberNotProvided();
        }

        if (empty($message['to'])) {
            throw CouldNotSendMessage::toNumberNotProvided();
        }

        if (empty($message['text'])) {
            throw CouldNotSendMessage::textNotProvided();
        }

        return $this->post('/account/~/extension/~/sms', [
            'from' => ['phoneNumber' => $message['from']],
            'to' => [
                ['phoneNumber' => $message['to']],
            ],
            'text' => $message['text'],
        ]);
    }

    public function getExtensions(): array {
        $r = $this->get('/account/~/extension');
        return $r->json('records');
    }

    protected function getMessages(string $extensionId, ?object $fromDate = null, ?object $toDate = null, ?int $perPage = 100): array {
        $dates = [];

        if ($fromDate) {
            $dates['dateFrom'] = $fromDate->format('c');
        }

        if ($toDate) {
            $dates['dateTo'] = $toDate->format('c');
        }

        $r = $this->get('/account/~/extension/'.$extensionId.'/message-store', array_merge(
            [
                'messageType' => 'SMS',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json('records');
    }

    public function getMessagesForExtensionId(string $extensionId, ?object $fromDate = null, ?object $toDate = null, ?int $perPage = 100): array {
        return $this->getMessages($extensionId, $fromDate, $toDate, $perPage);
    }

    public function getPhoneNumbers(): array {
        return $this->get('/account/~/phone-number')->json('records');
    }

    public function getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId): Response {
        return $this->get('/account/~/extension/'.$extensionId.'/message-store/'.$messageId.'/content/'.$attachementId);
    }

    public function getCallLogs(?object $fromDate = null, ?object $toDate = null, bool $withRecording = true, ?int $perPage = 100): array {
        $dates = [];

        if ($fromDate) {
            $dates['dateFrom'] = $fromDate->format('c');
        }

        if ($toDate) {
            $dates['dateTo'] = $toDate->format('c');
        }
        if ($withRecording) {
            $dates['recordingType'] = 'All';
        }

        $r = $this->get('/account/~/call-log', array_merge(
            [
                'type' => 'Voice',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json('records');
    }

    public function getCallLogsForExtensionId(string $extensionId, ?object $fromDate = null, ?object $toDate = null, bool $withRecording = true, ?int $perPage = 100): array {
        $dates = [];

        if ($fromDate) {
            $dates['dateFrom'] = $fromDate->format('c');
        }

        if ($toDate) {
            $dates['dateTo'] = $toDate->format('c');
        }

        if ($withRecording) {
            $dates['recordingType'] = 'All';
        }

        $r = $this->get('/account/~/extension/'.$extensionId.'/call-log', array_merge(
            [
                'type' => 'Voice',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json('records');
    }

    public function getRecordingById(string $recordingId): Response {
        return $this->get("https://media.ringcentral.com/restapi/v1.0/account/~/recording/{$recordingId}/content");
    }

    public function listWebhooks(): array {
        return $this->get('/subscription')->json('records');
    }

    public function createWebhook(array $filters, int $expiresIn, ?string $address, ?string $verificationToken): Response {
        return $this->post('/subscription', [
            'eventFilters' => $filters,
            'expiresIn' => $expiresIn,
            'deliveryMode' => [
                'transportType' => 'WebHook',
                'address' => $address,
            ],
        ]);
    }

    public function deleteWebhook(string $webhookId): Response {
        return $this->delete("/subscription/{$webhookId}");
    }
}
