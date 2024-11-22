<?php

namespace SheavesCapital\Ringcentral\Tests\Feature;

use Dotenv\Dotenv;
use Exception;
use SheavesCapital\RingCentral\Exceptions\CouldNotSendMessage;
use SheavesCapital\RingCentral\RingCentral;
use SheavesCapital\RingCentral\Tests\TestCase;

class RingCentralTest extends TestCase {
    protected RingCentral $ringCentral;

    protected function setUp(): void {
        parent::setUp();

        $this->loadEnvironmentVariables();

        $this->ringCentral = new RingCentral;

        $this->ringCentral
            ->setClientId(env('RINGCENTRAL_CLIENT_ID'))
            ->setClientSecret(env('RINGCENTRAL_CLIENT_SECRET'))
            ->setServerUrl(env('RINGCENTRAL_SERVER_URL'))
            ->setJwt(env('RINGCENTRAL_JWT'));

        $this->delay();
    }

    protected function loadEnvironmentVariables(): void {
        if (! file_exists(__DIR__.'/../../.env')) {
            return;
        }

        $dotenv = Dotenv::createImmutable(__DIR__.'/../..');

        $dotenv->load();
    }

    /** @test */
    public function it_can_retrieve_extensions(): void {
        $result = $this->ringCentral->getExtensions();

        $firstExtension = (array) $result[0];

        $this->assertArrayHasKey('id', $firstExtension);
        $this->assertArrayHasKey('extensionNumber', $firstExtension);
    }

    /** @test */
    public function it_can_send_an_sms_message(): void {
        $result = $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $this->assertEquals(
            env('RINGCENTRAL_IS_SANDBOX')
                ? 'Test SMS using a RingCentral Developer account - Test Message'
                : 'Test Message',
            $result->json()->subject
        );

        $this->assertEquals(env('RINGCENTRAL_RECEIVER'), $result->json()->to[0]->phoneNumber);
        $this->assertEquals(env('RINGCENTRAL_SENDER'), $result->json()->from->phoneNumber);
    }

    /** @test */
    public function it_can_retrieve_sent_sms_messages_for_a_given_extension_previous_24_hours(): void {
        $this->ringCentral->setLoggedInExtension();
        $extensionId = $this->ringCentral->loggedInExtensionId();

        $result = $this->ringCentral->getMessagesForExtensionId($extensionId);

        $firstMessage = (array) $result[0];

        $uriParts = explode('/', $firstMessage['uri']);
        $this->assertEquals($extensionId, $uriParts[8]);

        $this->assertArrayHasKey('id', $firstMessage);
        $this->assertArrayHasKey('to', $firstMessage);
        $this->assertArrayHasKey('from', $firstMessage);
        $this->assertArrayHasKey('subject', $firstMessage);
        $this->assertArrayHasKey('attachments', $firstMessage);
    }

    /** @test */
    public function it_can_retrieve_sent_sms_messages_for_a_given_extension_from_a_set_date(): void {
        $this->ringCentral->setLoggedInExtension();
        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $operatorExtensionId = $this->ringCentral->loggedInExtensionId();

        $result = $this->ringCentral->getMessagesForExtensionId(
            $operatorExtensionId,
            (new \DateTime)->modify('-1 mins')
        );

        $this->assertTrue(count($result) < 3);

        $firstMessage = (array) $result[0];

        $uriParts = explode('/', $firstMessage['uri']);
        $this->assertEquals($operatorExtensionId, $uriParts[8]);

        $this->assertArrayHasKey('id', $firstMessage);
        $this->assertArrayHasKey('to', $firstMessage);
        $this->assertArrayHasKey('from', $firstMessage);
        $this->assertArrayHasKey('subject', $firstMessage);
        $this->assertArrayHasKey('attachments', $firstMessage);
    }

    /** @test */
    public function it_can_retrieve_sent_sms_messages_for_a_given_extension_from_a_set_date_to_a_set_date(): void {
        $this->ringCentral->setLoggedInExtension();
        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $operatorExtensionId = $this->ringCentral->loggedInExtensionId();

        $result = $this->ringCentral->getMessagesForExtensionId(
            $operatorExtensionId,
            (new \DateTime)->modify('-1 mins'),
            (new \DateTime)->modify('+2 mins')
        );

        $this->assertNotEmpty($result);
        $this->assertTrue(count($result) < 10);

        $firstMessage = (array) $result[0];

        $uriParts = explode('/', $firstMessage['uri']);
        $this->assertEquals($operatorExtensionId, $uriParts[8]);

        $this->assertArrayHasKey('id', $firstMessage);
        $this->assertArrayHasKey('to', $firstMessage);
        $this->assertArrayHasKey('from', $firstMessage);
        $this->assertArrayHasKey('subject', $firstMessage);
        $this->assertArrayHasKey('attachments', $firstMessage);
    }

    /** @test */
    public function it_can_retrieve_sent_sms_messages_for_a_given_extension_with_per_page_limit_set(): void {
        $this->ringCentral->setLoggedInExtension();
        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $this->delay();

        $operatorExtensionId = $this->ringCentral->loggedInExtensionId();

        $result = $this->ringCentral->getMessagesForExtensionId(
            $operatorExtensionId,
            null,
            null,
            1
        );

        $this->assertSame(count($result), 1);

        $result = $this->ringCentral->getMessagesForExtensionId(
            $operatorExtensionId,
            null,
            null,
            2
        );

        $this->assertSame(count($result), 2);
    }

    /** @test */
    public function it_can_retrieve_an_sms_messages_attachement(): void {
        $this->ringCentral->setLoggedInExtension();
        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);

        $this->delay();

        $operatorExtensionId = $this->ringCentral->loggedInExtensionId();

        $result = $this->ringCentral->getMessagesForExtensionId(
            $operatorExtensionId,
            (new \DateTime)->modify('-1 mins')
        );

        $firstMessage = (array) $result[0];

        $attachment = $this->ringCentral->getMessageAttachmentById(
            $operatorExtensionId,
            $firstMessage['id'],
            $firstMessage['attachments'][0]->id
        );

        $this->assertNotNull($attachment->json());
    }

    /** @test */
    public function it_requires_a_from_number_to_send_an_sms_message(): void {
        $this->expectException(CouldNotSendMessage::class);

        $this->ringCentral->sendMessage([
            'to' => env('RINGCENTRAL_RECEIVER'),
            'text' => 'Test Message',
        ]);
    }

    /** @test */
    public function it_requires_a_to_number_to_send_an_sms_message(): void {
        $this->expectException(CouldNotSendMessage::class);

        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'text' => 'Test Message',
        ]);
    }

    /** @test */
    public function it_requires_a_to_message_to_send_an_sms_message(): void {
        $this->expectException(CouldNotSendMessage::class);

        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => env('RINGCENTRAL_RECEIVER'),
        ]);
    }

    /** @test */
    public function an_exception_is_thrown_if_message_not_sent(): void {
        $this->expectException(Exception::class);

        $this->ringCentral->sendMessage([
            'from' => env('RINGCENTRAL_SENDER'),
            'to' => 123,
            'text' => 'Test Message',
        ]);
    }
}
