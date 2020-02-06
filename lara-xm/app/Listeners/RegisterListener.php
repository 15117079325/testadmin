<?php

namespace App\Listeners;

use App\Events\RegisterEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class RegisterListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  RegisterEvent  $event
     * @return void
     */
    public function handle(RegisterEvent $event)
    {
        $user = $event->user;

        dd($event->data);
    }
}
