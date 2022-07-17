<?php

namespace App\Tests\Authorization;

use App\Service\BillingClient;
use App\Service\DecodingJwt;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;

class Auth extends AbstractTest
{
    private SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function auth(string $data)
    {
        $requestData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        $this->getBillingClient();
        $client = self::getClient();

        $crawler = $client->request('GET', '/login');
        $this->assertResponseOk();

        $form = $crawler->selectButton('Sign in')->form();
        $form['email'] = $requestData['username'];
        $form['password'] = $requestData['password'];
        $client->submit($form);

        $error = $crawler->filter('#errors');
        self::assertCount(0, $error);

        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        return $crawler;
    }

    public function getBillingClient(): void
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock( $this->serializer)
        );
    }
}