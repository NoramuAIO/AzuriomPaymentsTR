<?php

namespace Azuriom\Plugin\TurkishPayment;

class AkbankMethod extends NestpayMethod
{
    protected $id = 'akbank';
    protected $name = 'Akbank';

    protected function getEndpointUrl(bool $testMode): string
    {
        return $testMode 
            ? 'https://www.sanalakpos.com/est3Dgate' // Testing usually via Akbank test environment
            : 'https://www.sanalakpos.com/fim/est3Dgate';
    }

    public function view(): string
    {
        return 'turkishpayment::admin.akbank';
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/akbank.svg');
    }
}
