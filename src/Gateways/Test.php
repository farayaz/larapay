<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Support\Facades\View;

class Test extends GatewayAbstract
{
    protected $statuses = [];

    protected $requirements = [];

    public function request(int $id, int $amount, string $nationalId, string $mobile, string $callbackUrl): array
    {
        return [
            'token' => 'token',
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::test', [
            'id' => $id,
            'token' => $token,
            'callbackUrl' => $callbackUrl,
        ]);
    }

    public function verify(int $id, int $amount, string $nationalId, string $mobile, string $token, array $params): array
    {
        $default = [
            'status' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['status'] != 'successed') {
            throw new LarapayException($this->translateStatus($params['status']));
        }

        return [
            'result' => $params['status'],
            'card' => '123456******1234',
            'tracking_code' => random_int(1000, 9999),
            'reference_id' => random_int(10000000, 99999999),
            'fee' => 0,
        ];
    }
}
