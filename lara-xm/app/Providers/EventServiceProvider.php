<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
        'App\Events\RegisterEvent' => [
            'App\Listeners\RegisterListener'
        ],
        'App\Events\SingleLoginEvent' => [
            'App\Listeners\SingleLoginListener'
        ],
        'App\Events\CustomsMoneyEvent' => [
            'App\Listeners\CustomsMoneyListener'
        ],
        'App\Events\NewOrderEvent' => [
            'App\Listeners\NewOrderListener'
        ],
        'App\Events\UserPayEvent' => [
            'App\Listeners\UserPayListener'
        ],
        'App\Events\ExplosionOrderEvent' => [
            'App\Listeners\ExplosionOrderListener'
        ],
        'App\Events\UpdateOrderStatusEvent' => [
            'App\Listeners\UpdateOrderStatusListener'
        ],
        'App\Events\JihuolaoyonghuEvent' => [
            'App\Listeners\JihuolaoyonghuListener',
        ],
        'App\Events\GqchoujiangEvent' => [
            'App\Listeners\GqchoujiangListener'
        ],
        'App\Events\XinjiesuanluojiEvent' => [
            'App\Listeners\XinjiesuanluojiListener'
        ],
        'App\Events\XinbaodanluojiEvent' => [
            'App\Listeners\XinbaodanluojiListener'
        ],
        'App\Events\XinbaodanzuihouluojiEvent' => [
            'App\Listeners\XinbaodanzuihouluojiListener'
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
