<?php



namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\Authorization\Auth;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends AbstractTest
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    // Тесты авторизации пользователя в системе
    public function testAuth(): void
    {
        $crawler = $this->login('user@mail.ru', 'user123');
        $client = self::getClient();

        $this->assertResponseOk();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        $linkLogout = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($linkLogout);

        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        $crawler = $client->followRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());
    }
    private function login(string $username, string $password): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => $username,
            'password' => $password,
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }
}