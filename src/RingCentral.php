<?php

namespace Coxlr\RingCentral;

use Coxlr\RingCentral\Exceptions\CouldNotAuthenticate;
use Coxlr\RingCentral\Exceptions\CouldNotSendMessage;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\Http\ApiResponse;
use RingCentral\SDK\Platform\Platform;
use RingCentral\SDK\SDK;

class RingCentral {
    protected ?Platform $ringCentral = null;
    protected string $serverUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $loggedInExtension;
    protected string $loggedInExtensionId;
    protected ?string $token = null;

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

    public function setToken(string $token): static {
        $this->token = $token;

        return $this;
    }

    public function clientId(): string {
        return $this->clientId;
    }

    public function clientSecret(): string {
        return $this->clientSecret;
    }

    public function serverUrl(): string {
        return $this->serverUrl;
    }

    public function token(): string {
        return $this->token;
    }

    public function connect(): void {
        $this->ringCentral = (new SDK($this->clientId(), $this->clientSecret(), $this->serverUrl()))->platform();
    }

    public function login(): void {
        $this->ringCentral->login(['jwt' => $this->Token()]);
        $this->setLoggedInExtension();
    }

    public function setLoggedInExtension(): void {
        $extension = $this->ringCentral->get('/account/~/extension/~/')->json();
        $this->loggedInExtensionId = $extension->id;
        $this->loggedInExtension = $extension->extensionNumber;
    }

    public function loggedInExtensionId(): string {
        return $this->loggedInExtensionId;
    }

    public function loggedInExtension(): string {
        return $this->loggedInExtension;
    }

    /**
     * @throws CouldNotAuthenticate
     */
    public function authenticate(): bool {
        if (! $this->ringCentral) {
            $this->connect();
        }

        if (! $this->loggedIn()) {
            $this->login();
        }

        if (! $this->ringCentral->loggedIn()) {
            throw CouldNotAuthenticate::loginFailed();
        }

        return true;
    }

    /**
     * @throws ApiException
     */
    public function loggedIn(): bool {
        if ($this->ringCentral->loggedIn()) {
            return $this->ringCentral->get('/account/~/extension/~/')->json()->permissions->admin->enabled ?? false;
        }

        return false;
    }

    /**
     * @throws CouldNotSendMessage
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function sendMessage(array $message): ApiResponse {
        if (empty($message['from'])) {
            throw CouldNotSendMessage::toNumberNotProvided();
        }

        if (empty($message['to'])) {
            throw CouldNotSendMessage::toNumberNotProvided();
        }

        if (empty($message['text'])) {
            throw CouldNotSendMessage::textNotProvided();
        }

        $this->authenticate();

        return $this->ringCentral->post('/account/~/extension/~/sms', [
            'from' => ['phoneNumber' => $message['from']],
            'to' => [
                ['phoneNumber' => $message['to']],
            ],
            'text' => $message['text'],
        ]);
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getExtensions(): array {
        $this->authenticate();

        $r = $this->ringCentral->get('/account/~/extension');

        return $r->json()->records;
    }

    /**
     * @throws ApiException
     */
    protected function getMessages(string $extensionId, ?object $fromDate = null, ?object $toDate = null, ?int $perPage = 100): array {
        $dates = [];

        if ($fromDate) {
            $dates['dateFrom'] = $fromDate->format('c');
        }

        if ($toDate) {
            $dates['dateTo'] = $toDate->format('c');
        }

        $r = $this->ringCentral->get('/account/~/extension/'.$extensionId.'/message-store', array_merge(
            [
                'messageType' => 'SMS',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json()->records;
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getMessagesForExtensionId(string $extensionId, ?object $fromDate = null, ?object $toDate = null, ?int $perPage = 100): array {
        $this->authenticate();

        return $this->getMessages($extensionId, $fromDate, $toDate, $perPage);
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getPhoneNumbers(): array {
        $this->authenticate();

        return $this->ringCentral->get('/account/~/phone-number')->json()->records;
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId): ApiResponse {
        $this->authenticate();

        return $this->ringCentral->get('/account/~/extension/'.$extensionId.'/message-store/'.$messageId.'/content/'.$attachementId);
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getCallLogs(?object $fromDate = null, ?object $toDate = null, bool $withRecording = true, ?int $perPage = 100) {
        $this->authenticate();

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

        $r = $this->ringCentral->get('/account/~/call-log', array_merge(
            [
                'type' => 'Voice',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json()->records;
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getCallLogsForExtensionId(string $extensionId, ?object $fromDate = null, ?object $toDate = null, bool $withRecording = true, ?int $perPage = 100) {
        $this->authenticate();

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

        $r = $this->ringCentral->get('/account/~/extension/'.$extensionId.'/call-log', array_merge(
            [
                'type' => 'Voice',
                'perPage' => $perPage,
            ],
            $dates
        ));

        return $r->json()->records;
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getRecordingById(string $recordingId): ApiResponse {
        $this->authenticate();

        return $this->ringCentral->get("https://media.ringcentral.com/restapi/v1.0/account/~/recording/{$recordingId}/content");
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function listWebhooks(): array {
        $this->authenticate();

        return $this->ringCentral->get('/subscription')->json()->records;
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function createWebhook(array $filters, int $expiresIn, ?string $address, ?string $verificationToken): ApiResponse {
        $this->authenticate();

        return $this->ringCentral->post('/subscription', [
            'eventFilters' => $filters,
            'expiresIn' => $expiresIn,
            'deliveryMode' => [
                'transportType' => 'WebHook',
                'address' => $address,
            ],
        ]);
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function deleteWebhook(string $webhookId): ApiResponse {
        $this->authenticate();

        return $this->ringCentral->delete("/subscription/{$webhookId}");
    }
}
