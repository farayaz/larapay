<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class ECD extends GatewayAbstract
{
    protected $url = 'https://ecd.shaparak.ir/ipg_ecd/';

    protected $statuses = [];

    protected $requirements = ['terminal_number', 'hash_key'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $data = [
            'TerminalNumber' => $this->config['terminal_number'],
            'BuyID' => $id,
            'Amount' => $amount,
            'Date' => Date::now()->format('Y/m/d'),
            'Time' => Date::now()->format('H:m'),
            'RedirectURL' => $callbackUrl,
        ];
        $data['CheckSum'] = sha1(implode('', array_values($data)) . $this->config['hash_key']);
        $data['Language'] = 'fa';

        $result = $this->_request('PayRequest', $data);
        if (! empty($result['ErrorCode'])) {
            throw new LarapayException($this->translateStatus($result['ErrorDescription']));
        }

        return [
            'token' => $result['Res'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = $this->url . 'PayStart';
        $fields = [
            'Token' => $token,
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
            'State' => null,
            'Amount' => null,
            'ErrorCode' => null,
            'ErrorDescription' => null,
            'ReferenceNumber' => null,
            'TrackingNumber' => null,
            'BuyID' => null,
            'Token' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['Token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['BuyID'] != $token) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if ($params['Amount'] != $amount) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }
        if (! empty($params['ErrorCode'])) {
            throw new LarapayException($this->translateStatus($params['ErrorCode']));
        }

        $data = [
            'Token' => $token,
        ];
        $result = $this->_request('PayConfirmation', $data);
        if (! empty($result['ErrorCode'])) {
            throw new LarapayException($this->translateStatus($result['ErrorDescription']));
        }

        return [
            'result' => 'OK',
            'card' => null,
            'tracking_code' => $result['TrackingNumber'],
            'reference_id' => $result['ReferenceNumber'],
            'fee' => 0,
        ];
    }

    private function _request(string $path, array $data = [])
    {
        $url = $this->url . $path;
        try {
            return Http::timeout(10)
                ->withoutVerifying()
                ->post($url, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
