<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Bitpay extends GatewayAbstract
{
    protected $url = 'http://bitpay.ir/payment/';

    protected $statuses = [
        'trans-id-gt-0' => 'آیدی تراکنش صحیح نمی باشد',
        '1' => 'پرداخت موفق',
        '-1' => 'دسترسی غیرمجاز',
        '-2' => 'آیدی تراکنش معتبر نمی باشد',
        '-3' => 'توکن ارسالی معتبر نمی باشد',
        '-4' => 'تراکنش پیدا نشد یا موفقیت آمیز نبوده است',
        '11' => 'تراکنش از قبل تایید شده است',
    ];

    protected $requirements = ['api', 'sandbox'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $params = [
            'api' => $this->config['api'],
            'amount' => $amount,
            'redirect' => $callbackUrl,
            'name' => 'موبایل : ' . $mobile,
            'email' => '',
            'description' => $nationalId,
            'factorId' => $id,
        ];
        $result = $this->_request('post', 'gateway-send', $params);

        if ($result < 0) {
            throw new LarapayException($this->translateStatus($result));
        }

        return [
            'token' => $result,
            'fee' => 0,
        ];
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
            'trans_id' => null,
            'id_get' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['id_get'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['trans_id'] < 0) {
            throw new LarapayException($this->translateStatus('trans-id-gt-0'));
        }

        $data = [
            'api' => $this->config['api'],
            'trans_id' => $params['trans_id'],
            'id_get' => $params['id_get'],
            'json' => 1,
        ];

        $result = $this->_request('post', 'gateway-result-second', $data);

        if ($result['status'] != 1) {
            throw new LarapayException($this->translateStatus($result['status']));
        }

        return [
            'result' => $this->translateStatus($result['status']),
            'card' => $result['cardNum'],
            'tracking_code' => $result['factorId'],
            'reference_id' => $params['id_get'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->_url('gateway-' . $token . '-get'));
    }

    private function _request(string $method, string $path, array $data = [], array $headers = [], $timeout = 10)
    {
        $url = $this->_url($path);

        if ($this->config['sandbox']) {
            $data['api'] = 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567';
        }

        try {
            $response = Http::timeout($timeout)
                ->asForm()
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
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    protected function _url($path)
    {
        $url = 'http://bitpay.ir/payment';
        if ($this->config['sandbox']) {
            $url .= '-test';
        }

        return $url . '/' . $path;
    }
}
