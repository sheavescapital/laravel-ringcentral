<?php

namespace SheavesCapital\RingCentral;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use SheavesCapital\RingCentral\Enums\CallDirection;
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
    protected string $verification_token;
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

    public function setVerificationToken(string $verification_token): static {
        $this->verification_token = $verification_token;
        return $this;
    }

    protected function login(): string {
        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->serverUrl.self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt,
                'access_token_ttl' => self::ACCESS_TOKEN_TTL,
                'refresh_token_ttl' => self::REFRESH_TOKEN_TTL,
            ]);
        $response->throw();
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
        $response->throw();
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
        $response->throw();
        return $response;
    }

    public function post(string $url, array $body = [], array $headers = [], bool $prependPath = true): Response {
        $response = Http::withToken($this->accessToken())
            ->withHeaders($headers)
            ->post(
                $prependPath ? $this->prependPath($url) : $url,
                $body
            );
        $response->throw();
        return $response;
    }

    public function delete(string $url, array $query = [], array $headers = [], bool $prependPath = true): Response {
        $response = Http::withToken($this->accessToken())
            ->withHeaders($headers)
            ->delete(
                $prependPath ? $this->prependPath($url) : $url,
                $query
            );
        $response->throw();
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

    public function getExtensions(): Collection {
        $r = $this->get('/account/~/extension');
        return $r->collect('records');
    }

    public function getExtensionMap(): Collection {
        return Cache::flexible('ringcentral_extension_map', [86400, 259200], function () {
            return RingCentral::getExtensions()
                ->mapWithKeys(function (array $extension) {
                    return [$extension['id'] => $extension['contact']['email']];
                });
        });
    }

    protected function getMessages(string $extensionId, ?Carbon $fromDate = null, ?Carbon $toDate = null, ?int $perPage = 100): Collection {
        $data = [
            'messageType' => 'SMS',
            'perPage' => $perPage,
        ];
        if ($fromDate) {
            $dtat['dateFrom'] = $fromDate->toIso8601String();
        }
        if ($toDate) {
            $data['dateTo'] = $toDate->toIso8601String();
        }
        $r = $this->get('/account/~/extension/'.$extensionId.'/message-store', $data);
        return $r->collect('records');
    }

    public function getMessagesForExtensionId(string $extensionId, ?Carbon $fromDate = null, ?Carbon $toDate = null, ?int $perPage = 100): Collection {
        return $this->getMessages($extensionId, $fromDate, $toDate, $perPage);
    }

    public function getPhoneNumbers(): Collection {
        return $this->get('/account/~/phone-number')->collect('records');
    }

    public function getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId): Response {
        return $this->get('/account/~/extension/'.$extensionId.'/message-store/'.$messageId.'/content/'.$attachementId);
    }

    public function getCallLogs(?Carbon $fromDate = null, ?Carbon $toDate = null, bool $withRecording = true, ?int $perPage = 100): Collection {
        $data = [
            'type' => 'Voice',
            'perPage' => $perPage,
        ];
        if ($fromDate) {
            $data['dateFrom'] = $fromDate->toIso8601String();
        }
        if ($toDate) {
            $dtat['dateTo'] = $toDate->toIso8601String();
        }
        if ($withRecording) {
            $dtat['recordingType'] = 'All';
        }
        $r = $this->get('/account/~/call-log', $data);
        return $r->collect('records');
    }

    public function getCallLogsForExtensionId(string $extensionId, ?Carbon $fromDate = null, ?Carbon $toDate = null, bool $withRecording = true, ?int $perPage = 100): Collection {
        $data = [
            'type' => 'Voice',
            'perPage' => $perPage,
        ];
        if ($fromDate) {
            $data['dateFrom'] = $fromDate->toIso8601String();
        }
        if ($toDate) {
            $dtat['dateTo'] = $toDate->toIso8601String();
        }
        if ($withRecording) {
            $dtat['recordingType'] = 'All';
        }
        $r = $this->get('/account/~/extension/'.$extensionId.'/call-log', $data);
        return $r->collect('records');
    }

    public function getRecordingById(string $recordingId): Response {
        return $this->get("https://media.ringcentral.com/restapi/v1.0/account/~/recording/{$recordingId}/content", prependPath: false);
    }

    public function saveRecordingById(string $recordingId, ?string $disk = null, string $path = ''): string|false {
        $response = $this->getRecordingById($recordingId);
        $ext = ($response->header('Content-Type') == 'audio/mpeg') ? '.mp3' : '.wav';
        $path = trim($path, '/').'/'.Str::random(2).'/'.Str::random(40).$ext;
        $result = Storage::disk($disk)->put($path, $response->body());
        return $result ? $path : false;
    }

    public function listWebhooks(): Collection {
        return $this->get('/subscription')->collect('records');
    }

    public function createWebhook(array $filters, int $expiresIn, string $address): Response {
        $data = [
            'eventFilters' => $filters,
            'expiresIn' => $expiresIn,
            'deliveryMode' => [
                'transportType' => 'WebHook',
                'address' => $address,
            ],
        ];
        if ($this->verification_token) {
            $data['deliveryMode']['verificationToken'] = $this->verification_token;
        }
        return $this->post('/subscription', $data);
    }

    public function deleteWebhook(string $webhookId): Response {
        return $this->delete("/subscription/{$webhookId}");
    }

    public function verifyWebhook(Request $request): bool {
        return $request->header('verification-token') == $this->verification_token;
    }

    public function parseWebhookBody(Request $request): Fluent {
        $sessionId = $request->input('body.sessionId');
        $timestamp = $request->date('timestamp');
        $direction = $request->enum('body.parties.0.direction', CallDirection::class);
        $extensionId = $request->input('body.parties.0.extensionId');
        $extensionEmail = $this->getExtensionMap()->get($extensionId);
        $recordingId = $request->input('body.parties.0.recordings.id');
        $externalKey = $direction == CallDirection::INBOUND ? 'from' : 'to';
        $externalPhoneNumber = $request->string("body.parties.0.{$externalKey}.phoneNumber")->ltrim('+1');
        return fluent([
            'sessionId' => $sessionId,
            'timestamp' => $timestamp,
            'direction' => $direction,
            'extensionId' => $extensionId,
            'extensionEmail' => $extensionEmail,
            'recordingId' => $recordingId,
            'externalPhoneNumber' => $externalPhoneNumber,
        ]);
    }

    public function parseCallRecordArray(array $record): Fluent {
        $record = new Fluent($record);
        $sessionId = $record->get('sessionId');
        $timestamp = Carbon::parse($record['startTime']);
        $direction = CallDirection::from($record->get('direction'));
        $extensionId = $record->get('extension.id');
        $extensionEmail = $this->getExtensionMap()->get($extensionId);
        $recordingId = $record->get('recording.id');
        $externalKey = $direction == CallDirection::INBOUND ? 'from' : 'to';
        $externalPhoneNumber = Str::ltrim($record->get("{$externalKey}.phoneNumber"), '+1');
        return fluent([
            'sessionId' => $sessionId,
            'timestamp' => $timestamp,
            'direction' => $direction,
            'extensionId' => $extensionId,
            'extensionEmail' => $extensionEmail,
            'recordingId' => $recordingId,
            'externalPhoneNumber' => $externalPhoneNumber,
        ]);
    }
}
