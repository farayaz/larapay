<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Support\Facades\Redirect;
use SoapClient;
use SoapFault;

class PEC extends GatewayAbstract
{
    protected $statuses = [
        '-126' => 'کد شناسایی پذیرنده معتبر نمی‌باشد',
    ];

    protected $requirements = ['login_account'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        ini_set('default_socket_timeout', 10);
        $data = [
            'requestData' => [
                'LoginAccount' => $this->config['login_account'],
                'Amount' => $amount,
                'OrderId' => $id,
                'CallBackUrl' => $callbackUrl,
                'AdditionalData' => $id,
                'Originator' => $mobile,
            ],
        ];
        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?WSDL');
            $response = $client->SalePaymentRequest($data);
        } catch (SoapFault $e) {
            throw new LarapayException($e->getMessage());
        }
        $result = $response->SalePaymentRequestResult;
        if ($result->Status != 0 || $result->Token <= 0) {
            throw new LarapayException($result->Message);
        }

        return [
            'token' => $result->Token,
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://pec.shaparak.ir/NewIPG/?Token=' . $token);
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
            'Token' => null,
            'Status' => null,
            'OrderId' => null,
            'TerminalNo' => null,
            'RRN' => null,
            'HashCardNumber' => null,
            'Amount' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['Token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['Amount'] != $amount) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }
        if ($params['Status'] != 0) {
            throw new LarapayException($this->translateStatus($params['Status'] ?: 'failed'));
        }

        ini_set('default_socket_timeout', 10);
        $data = [
            'requestData' => [
                'LoginAccount' => $this->config['login_account'],
                'Token' => $token,
            ],
        ];
        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?WSDL');
            $response = $client->ConfirmPayment($data);
        } catch (SoapFault $e) {
            throw new LarapayException($e->getMessage());
        }
        $result = $response->ConfirmPaymentResult;

        if ($result->Status != 0 || $result->RNN <= 0) {
            throw new LarapayException($this->translateStatus($result->Status));
        }

        return [
            'card' => $result->CardNumberMasked,
            'tracking_code' => $result->RNN,
            'reference_id' => $result->RNN,
            'result' => $result->Status,
            'fee' => 0,
        ];
    }
}
