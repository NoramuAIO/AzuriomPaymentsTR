<?php

namespace Azuriom\Plugin\TurkishPayment;

class IsbankMethod extends NestpayMethod
{
    protected $id = 'isbank';
    protected $name = 'İş Bankası';

    protected function getEndpointUrl(bool $testMode): string
    {
        return $testMode 
            ? 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate'
            : 'https://sanalpos.isbank.com.tr/fim/est3Dgate';
    }

    public function view(): string
    {
        return 'turkishpayment::admin.isbank';
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/isbank.svg');
    }
}
