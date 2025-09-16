<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class IsipaymentSamin extends GatewayAbstract
{
    private $url = 'https://ipg.isipayment.ir/';

    protected $statuses = [];

    protected $requirements = [
        'merchant_code',
        'merchant_password',
        'terminal_code',
        'type',
        'number_of_installment',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $url = $this->url . 'api/IPGRequestPurchase';
        $params = [
            'Amount' => $amount,
            'PurchaseDate' => Carbon::now()->format('c'),
            'MerchantReferenceNumber' => $id,
            'ReturnURL' => $callbackUrl,
            'Type' => $this->config['type'],
            'NumberOfInstallment' => $this->config['number_of_installment'],
        ];

        $result = $this->_request($url, $params);
        if ($result['ResponseCode'] != 0) {
            throw new LarapayException($result['ResponseInformation']);
        }

        return [
            'token' => $result['Token'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . 'IPG?Token=' . $token);

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
            'ResponseCode' => null,
            'ResponseInformation' => null,
            'Token' => null,
            'RefNO' => null,
            'MerchantReferenceNumber' => null,
        ];
        $params = array_merge($default, $params);

        if ($token != $params['Token']) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        if ($params['ResponseCode'] != 0) {
            $message = $this->translateStatus($params['ResponseInformation'] ?? $params['ResponseCode']);
            throw new LarapayException($message);
        }

        $url = $this->url . 'api/ConfirmTransaction';
        $data = [
            'Token' => $token,
            'RefNO' => $params['RefNO'],
            'CONFIRM_TRANSACTION_STATUS' => 1,
        ];
        $result = $this->_request($url, $data);
        if ($result['ResponseCode'] != 0) {
            throw new LarapayException($this->translateStatus($result['ResponseInformation']));
        }

        return [
            'result' => $result['ResponseCode'],
            'card' => null,
            'reference_id' => $result['RefNO'],
            'tracking_code' => $result['RefNO'],
            'fee' => 0,
        ];
    }

    private function _request(string $url, array $data)
    {
        $data['MerchantCode'] = $this->config['merchant_code'];
        $data['MerchantPassword'] = $this->config['merchant_password'];
        $data['TerminalCode'] = $this->config['terminal_code'];

        try {
            return Http::timeout(10)
                ->post($url, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = $e->getMessage();

            if ($e->response->status() == 400) {
                $result = $e->response->json();
                $message = Arr::join(Arr::flatten($result['ModelState']), ' ');
            }

            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
