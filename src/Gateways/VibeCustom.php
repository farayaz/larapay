<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Ramsey\Uuid\Uuid;

class VibeCustom extends GatewayAbstract
{
    protected $requirements = ['api_key'];

    private const BASE_URL = 'https://credit.vibe.ir/cpg-custom/api/v1/orders';

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

        $order = $this->send('post', '/', [
            'order_id' => $orderId,
            'cart_amount' => $amount,
            // 'installments' => 12,
            'data' => [
                'goods_amount' => $amount,
                'tax' => 0,
                'items' => [
                    [
                        'id' => '1',
                        'name' => 'transaction',
                        'price' => $amount,
                        'quantity' => 1,
                        'discount' => 0,
                    ],
                ],
            ],
        ]);

        $token = $order['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new LarapayException('توکن سفارش وایب دریافت نشد.');
        }

        $inquiry = $this->send('post', '/inquiry', [
            'token' => $token,
            'national_code' => $nationalId,
        ]);
        $balance = (int) ($inquiry['data']['charge'] ?? 0);
        if ($balance < $amount) {
            throw new LarapayException(
                'وایب: اعتبار مشتری برای پرداخت کافی نیست. موجودی: '
                . number_format($balance) . ' ریال'
            );
        }

        return ['token' => $token, 'fee' => 0];
    }

    public function redirect(int|string $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::otp', compact('callbackUrl'));
    }

    public function verify(
        int|string $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array {
        if (empty($params['otp'])) {
            throw new LarapayException('کد پیامکی وایب الزامی است.');
        }

        $this->send('post', '/register', ['token' => $token, 'otp' => $params['otp']]);
        $this->send('get', '/' . rawurlencode($token) . '/verify');

        return [
            'result' => 'OK',
            'card' => null,
            'tracking_code' => $token,
            'reference_id' => $token,
            'fee' => 0,
        ];
    }

    private function send(string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $this->config['api_key']])
                ->$method(self::BASE_URL . $path, $data)
                ->throw()
                ->json();

            if (($response['status'] ?? false) !== true) {
                throw new LarapayException($response['message'] ?? 'درخواست وایب ناموفق بود.');
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
