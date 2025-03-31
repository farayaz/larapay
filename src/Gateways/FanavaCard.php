<?php

namespace Farayaz\Larapay\Gateways;

use Illuminate\Support\Facades\View;

class FanavaCard extends Omidpay
{
    protected $url = 'https://fcp.shaparak.ir/ref-payment/RestServices/mts/';

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = 'https://fcp.shaparak.ir/_ipgw_/payment/';
        $fields = [
            'token' => $token,
            'language' => 'fa',
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
    }
}
