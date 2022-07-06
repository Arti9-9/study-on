<?php

namespace App\Service;

use App\Dto\Transformer\UserAuthDtoTransformer;
use App\Dto\UserAuthDto;
use App\Dto\UserCurrentDto;
use App\Exception\BillingException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Serializer\SerializerInterface;

class BillingClient
{
    private $serializer;

    public function __construct(SerializerInterface $serializer){
    $this->serializer = $serializer;

}
    public function auth($credentials)
    {
        $api = new ApiService(
            '/api/v1/auth',
            'POST',
            $credentials,
            null,
            null,
            'Сервис авторизации недоступен. Попробуйте авторизоваться позже.');
        $response = $api->exec();
        $result = json_decode($response, true);
        if (isset($result['code'])) {
            if ($result['code'] === 401) {
                throw new UserNotFoundException('Проверьте правильность введённого логина и пароля');
            }
        }

        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');


        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

    public function register($registerRequest)
    {

        $api = new ApiService(
            '/api/v1/register',
            'POST',
            json_encode($registerRequest, true),
            null,
            null,
            'Сервис регистрации недоступен. Попробуйте зарегистрироваться позже.');
        $response = $api->exec();
        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }
        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');

        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }
    public function getUser($token)
    {
        $api = new ApiService(
            '/api/v1/users/current',
            'GET',
            null,
            null,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
            'Сервис биллинга недоступен.'
        );
        $response = $api->exec();

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingException(json_encode($result['errors']));
        }

        return $this->serializer->deserialize($response, UserCurrentDto::class, 'json');
    }
}
