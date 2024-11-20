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
    protected ?string $adminExtension = null;
    protected string $loggedInExtension;
    protected string $loggedInExtensionId;
    protected ?string $adminToken = null;

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

    public function setAdminToken(string $adminToken): static {
        $this->adminToken = $adminToken;

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

    public function adminExtension(): ?string {
        return $this->adminExtension;
    }

    public function adminToken(): string {
        return $this->adminToken;
    }

    public function connect(): void {
        $this->ringCentral = (new SDK($this->clientId(), $this->clientSecret(), $this->serverUrl()))->platform();
    }

    public function loginAdmin(): void {
        $this->login($this->adminToken());

        $this->setAdminExtension($this->loggedInExtension());
    }

    public function setAdminExtension(string $adminExtension): void {
        $this->adminExtension = $adminExtension;
    }

    public function login(string $token): void {
        $this->ringCentral->login(['jwt' => $token]);

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
    public function authenticateAdmin(): bool {
        if (! $this->ringCentral) {
            $this->connect();
        }

        if (! $this->adminLoggedIn()) {
            $this->loginAdmin();
        }

        if (! $this->ringCentral->loggedIn()) {
            throw CouldNotAuthenticate::adminLoginFailed();
        }

        return true;
    }

    /**
     * @throws ApiException
     */
    public function adminLoggedIn(): bool {
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

        $this->authenticateAdmin();

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
        $this->authenticateAdmin();

        $r = $this->ringCentral->get('/account/~/extension');

        return $r->json()->records;
    }

    /**
     * @throws ApiException
     */
    public function getMessages(string $extensionId, ?object $fromDate = null, ?object $toDate = null, ?int $perPage = 100): array {
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
        $this->authenticateAdmin();

        return $this->getMessages($extensionId, $fromDate, $toDate, $perPage);
    }

    /**
     * @throws CouldNotAuthenticate
     * @throws ApiException
     */
    public function getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId): ApiResponse {
        $this->authenticateAdmin();

        return $this->ringCentral->get('/account/~/extension/'.$extensionId.'/message-store/'.$messageId.'/content/'.$attachementId);
    }
}
