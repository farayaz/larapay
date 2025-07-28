<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Farayaz\Larapay\Interfaces\BulkCheckableInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Morilog\Jalali\Jalalian;

class RefahBeta extends GatewayAbstract implements BulkCheckableInterface
{
    protected $statuses = [
        'invalid_client' => 'invalid_client: خطای سرویس گیرنده',
        'not-enough-credit' => 'اعتبار کافی نیست، اعتبار ماهانه: ',
        'rial' => ' ریال',
    ];

    protected $requirements = [
        'client_id',
        'client_secret',
        'api_key',
        'number_of_installments',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {

        $url = 'beta/1.0/credit/' . $nationalId . '/inquiry';
        $result = $this->_request('post', $url);
        if ($result['status'] != 200) {
            throw new LarapayException($result['message']);
        }
        if ($amount / $this->config['number_of_installments'] > $result['data']['credit']) {
            $message = $this->translateStatus('not-enough-credit');
            $message .= number_format($result['data']['credit']);
            $message .= $this->translateStatus('rial');
            throw new LarapayException($message);
        }

        $url = 'beta/1.0/credit/' . $nationalId . '/request';
        $startDate = Jalalian::now();
        if ($startDate->getDay() > 15) {
            $startDate = $startDate->addMonths();
        }
        $data = [
            'title' => 'transaction' . $id,
            'startDate' => $startDate->getEndDayOfMonth()->toCarbon()->toIso8601String(),
            'amount' => $amount,
            'numberOfInstallments' => $this->config['number_of_installments'],
        ];
        $result = $this->_request('post', $url, $data);
        if ($result['status'] != 200) {
            throw new LarapayException($result['message']);
        }

        return [
            'token' => $id,
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::otp', compact('callbackUrl'));
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
            'otp' => null,
        ];
        $params = array_merge($default, $params);

        $url = 'beta/1.0/credit/' . $nationalId . '/consume';
        $startDate = Jalalian::now();
        if ($startDate->getDay() > 15) {
            $startDate = $startDate->addMonths();
        }
        $data = [
            'otp' => $params['otp'],
            'title' => 'transaction' . $id,
            'startDate' => $startDate->getEndDayOfMonth()->toCarbon()->toIso8601String(),
            'amount' => $amount,
            'numberOfInstallments' => $this->config['number_of_installments'],
            'requestId' => (string) $id,
        ];
        $result = $this->_request('post', $url, $data, [], 20);
        if ($result['status'] != 200) {
            throw new LarapayException($result['message']);
        }

        return [
            'result' => 'OK',
            'card' => null,
            'tracking_code' => $result['data'],
            'reference_id' => $result['data'],
            'fee' => 0,
        ];
    }

    public function bulkCheck(callable $successCallback, callable $unsuccessCallback): void
    {
        $result = $this->_request('get', 'beta/1.0/order/' . Jalalian::now()->format('Ym') . '/all', [], [], 30);
        $items = collect($result['data']['data'])->sortByDesc('id');
        foreach ($items->take(50) as $item) {
            if (! str_starts_with($item['title'], 'transaction')) {
                continue;
            }
            $id = str_replace(['transaction', '(حذف شده)'], '', $item['title']);
            $result = $this->_request('get', 'beta/1.0/order/' . $item['id'] . '/installments');
            $deletedsRecords = collect($result['data']['data'])->filter(function ($item) {
                return $item['isDeleted'];
            });
            if ($deletedsRecords->count() == 0) {
                $successCallback(
                    $id,
                    [
                        'result' => 'successed',
                        'card' => null,
                        'tracking_code' => $item['id'],
                        'reference_id' => $item['id'],
                        'amount' => (int) $item['amount'],
                        'fee' => 0,
                    ]
                );
            } elseif ($deletedsRecords->count() == $result['data']['totalRecords']) {
                $unsuccessCallback($id);
            }
        }
    }

    private function authenticate()
    {
        if (Cache::get(__CLASS__ . 'token')) {
            return Cache::get(__CLASS__ . 'token');
        }

        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'client_credentials',
        ];
        $result = $this->_request('post', 'connect/token', $data);

        Cache::put(__CLASS__ . 'token', $result['access_token'], $result['expires_in'] - 10);

        return $result['access_token'];
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $fullUrl = 'https://api.rb24.ir/' . $url;
        $as = 'asJson';

        if ($url == 'connect/token') {
            $as = 'asForm';
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
            $headers['apikey'] = $this->config['api_key'];
        }

        try {
            return Http::timeout($timeout)
                ->$as()
                ->withHeaders($headers)
                ->$method($fullUrl, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = $e->getMessage();
            $result = $e->response->json();
            if (isset($result['title'])) {
                $message = $result['title'];
            }
            if (isset($result['errors'])) {
                $message = implode(', ', array_merge(...array_values($result['errors'])));
            }
            throw new LarapayException('بتا رفاه: ' . $message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'), LarapayException::CODE_CONNECTION_EXCEPTION);
        }
    }
}
