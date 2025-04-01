<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class AsanPardakht extends GatewayAbstract
{
    protected $statuses = [
        'http-401' => 'Unauthorized',
    ];

    protected $requirements = [
        'username',
        'password',
        'merchant_configuration_id',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $params = [
            'serviceTypeId' => 1,
            'localInvoiceId' => $id,
            'amountInRials' => $amount,
            'localDate' => Date::now()->format('Ymd His'),
            'callbackURL' => $callbackUrl,
            'additionalData' => '',
            'paymentId' => 0,
        ];
        $result = $this->_request('post', 'Token', $params);

        return [
            'token' => $result,
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = 'https://asan.shaparak.ir';
        $fields = [
            'RefId' => $token,
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
    }

    public function verify(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array {
        $default = [
            'PaygateTranId' => null,
            'MerchantShaparakFee' => null,
            'ReturningParams' => null,
        ];
        $params = array_merge($default, $params);

        if (count(array_filter($params)) != 3) {
            throw new LarapayException($this->translateStatus('failed'));
        }

        $data = [
            'localInvoiceId' => $id,
        ];
        $result = $this->_request('get', 'TranResult', $data);
        if ($result['payGateTranID'] != $params['PaygateTranId']) {
            throw new LarapayException($this->translateStatus('token-missmatch'));
        }
        // if ($result['serviceStatusCode'] != 0) {
        //     throw new LarapayException($this->translateStatus('failed'));
        // }

        $data = [
            'payGateTranId' => $result['payGateTranId'],
        ];
        $this->_request('post', 'Verify', $data);
        $this->_request('post', 'Settlement', $data);

        return [
            'fee' => 0,
            'card' => $result['cardNumber'],
            'result' => 'OK',
            'reference_id' => $result['rrn'],
            'tracking_code' => $result['payGateTranId'],
        ];
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $url = 'https://ipgrest.asanpardakht.ir/v1/' . $url;
        $data['merchantConfigurationId'] = $this->config['merchant_configuration_id'];
        $headers = [
            'usr' => $this->config['username'],
            'pwd' => $this->config['password'],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw();

            if (str_contains($response->header('Content-Type'), 'application/json')) {
                $result = $response->json();
            } else {
                $result = $response->body();
            }

            return $result;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->response) {
                if ($e->response->status() == 401) {
                    $message = $this->translateStatus('http-401');
                }
                if ($e->response->status() == 571) {
                    $result = $e->response->json();
                    $message = $this->translateStatus($result['error']['message']);
                }
            }
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
