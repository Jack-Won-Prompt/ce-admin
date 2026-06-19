<?php

namespace App\Providers;

use App\Services\Popbill\CashbillService;
use App\Services\Popbill\FaxService;
use App\Services\Popbill\KakaoService;
use App\Services\Popbill\MessageService;
use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Support\ServiceProvider;

class PopbillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!defined('LINKHUB_COMM_MODE')) {
            define('LINKHUB_COMM_MODE', config('popbill.LINKHUB_COMM_MODE', 'CURL'));
        }

        $this->app->singleton(TaxinvoiceService::class);
        $this->app->singleton(CashbillService::class);
        $this->app->singleton(KakaoService::class);
        $this->app->singleton(MessageService::class);
        $this->app->singleton(FaxService::class);
    }
}
