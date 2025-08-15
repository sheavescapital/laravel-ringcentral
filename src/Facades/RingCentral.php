<?php

namespace SheavesCapital\RingCentral\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \SheavesCapital\RingCentral\RingCentral setClientId(string $clientId)
 * @method static \SheavesCapital\RingCentral\RingCentral setClientSecret(string $clientSecret)
 * @method static \SheavesCapital\RingCentral\RingCentral setServerUrl(string $serverUrl)
 * @method static \SheavesCapital\RingCentral\RingCentral setjWT(string $jwt)
 * @method static \SheavesCapital\RingCentral\RingCentral setVerificationToken(string $verification_token)
 * @method static \Illuminate\Http\Client\Response get(string $url, array $query = [], array $headers = [], bool $prependPath = true)
 * @method static \Illuminate\Http\Client\Response post(string $url, array $body = [], array $headers = [], bool $prependPath = true)
 * @method static \Illuminate\Http\Client\Response delete(string $url, array $query = [], array $headers = [], bool $prependPath = true)
 * @method static void setLoggedInExtension()
 * @method static string loggedInExtensionId()
 * @method static string loggedInExtension()
 * @method static \Illuminate\Http\Client\Response sendMessage(array $message)
 * @method static \Illuminate\Support\Collection getExtensions()
 * @method static \Illuminate\Support\Collection getExtensionMap()
 * @method static \Illuminate\Support\Collection getPhoneNumbers()
 * @method static \Illuminate\Support\Collection getPhoneNumberMap()
 * @method static \Illuminate\Support\Collection getMessagesForExtensionId(string $extensionId, \Illuminate\Support\Carbon|null $fromDate = null, \Illuminate\Support\Carbon|null $toDate = null, int|null $perPage = 100)
 * @method static \Illuminate\Http\Client\Response getMessageAttachmentById(string $extensionId, string $messageId, string $attachementId)
 * @method static \Illuminate\Support\Collection getCallLogs(\Illuminate\Support\Carbon|null $fromDate = null, \Illuminate\Support\Carbon|null $toDate = null, bool $withRecording = true, int|null $perPage = 100, string|null $sessionId = null)
 * @method static \Illuminate\Support\Collection getCallLogsForExtensionId(string $extensionId, \Illuminate\Support\Carbon|null $fromDate = null, \Illuminate\Support\Carbon|null $toDate = null, bool $withRecording = true, int|null $perPage = 100)
 * @method static \Illuminate\Http\Client\Response getRecordingById(string $recordingId)
 * @method static string|false saveRecordingById(string $recordingId, string|null $disk = null, string $path = '')
 * @method static \Illuminate\Http\Client\Response transferToExtension(string $telephonySessionId, string $partyId, string $extension)
 * @method static \Illuminate\Support\Collection listWebhooks()
 * @method static \Illuminate\Support\Collection renewWebhook(string $id)
 * @method static \Illuminate\Support\Collection renewExpiringWebhooks(int $seconds)
 * @method static \Illuminate\Http\Client\Response createWebhook(array $filters, int $expiresIn, string $address)
 * @method static \Illuminate\Http\Client\Response deleteWebhook(string $webhookId)
 * @method static bool verifyWebhook(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Support\Fluent parseAnsweredWebhookBody(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Support\Fluent parseRecordingWebhookBody(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Support\Fluent parseCallRecordArray(array $record)
 *
 * @see \SheavesCapital\RingCentral\RingCentral
 */
class RingCentral extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'ringcentral';
    }
}
