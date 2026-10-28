<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Ramsey\Uuid\Uuid;

class Vibe extends GatewayAbstract
{
    protected $requirements = ['api_key'];

    private const BASE_URL = 'https://credit.vibe.ir/merchants/api/v1/orders';

    public function request(
        int|string $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $orderId = Uuid::isValid((string) $id)
            ? (string) $id
            : Uuid::uuid5(Uuid::NAMESPACE_DNS, 'larapay:vibe:' . $id)->toString();

        $order = $this->post('/', [
            'order_id' => $orderId,
            'cart_amount' => $amount,
            'callback_url' => $callbackUrl,
            'data' => [
                'goods_amount' => $amount,
                'tax' => 0,
                'items' => [[
                    'id' => '1',
                    'name' => 'transaction',
                    'price' => $amount,
                    'quantity' => 1,
                    'discount' => 0,
                ]],
            ],
        ]);

        $paymentUrl = $order['payment_url'] ?? null;
        if (! is_string($paymentUrl) || ! str_starts_with($paymentUrl, 'https://')) {
            throw new LarapayException('لینک پرداخت معتبر از وایب دریافت نشد.');
        }

        return [
            'token' => $orderId,
            'fee' => 0
        ];
    }

    public function redirect(int|string $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://vibe.ir/credit-payment?t=' . rawurlencode($token));
    }

    public function verify(
        int|string $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array {
        if (($params['ref_id'] ?? null) !== $token) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if (($params['result'] ?? null) !== 'OK') {
            throw new LarapayException($this->translateStatus('failed'));
        }

        $result = $this->post('/verify', ['ref_id' => $token]);
        if (($result['status'] ?? false) !== true) {
            throw new LarapayException($result['message'] ?? $this->translateStatus('failed'));
        }

        return [
            'result' => 'OK',
            'card' => null,
            'tracking_code' => $token,
            'reference_id' => $token,
            'fee' => 0,
        ];
    }

    private function post(string $path, array $data): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $this->config['api_key']])
                ->post(self::BASE_URL . $path, $data)
                ->throw()
                ->json();

            if (! is_array($response)) {
                throw new LarapayException('پاسخ نامعتبر از سرویس وایب دریافت شد.');
            }

            return $response;
        } catch (RequestException $exception) {
            $message = $exception->response->json('detail');
            throw new LarapayException(is_string($message) ? $message : $exception->getMessage(), 0, $exception);
        } catch (ConnectionException $exception) {
            throw new LarapayException($this->translateStatus('connection-exception'), 0, $exception);
        }
    }
}
