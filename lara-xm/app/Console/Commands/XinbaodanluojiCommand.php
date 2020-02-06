<?php

namespace App\Console\Commands;

use App\Events\XinjiesuanluojiEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Events\XinbaodanluojiEvent;
use App\Events\XinbaodanzuihouluojiEvent;

class XinbaodanluojiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baodan:xinde';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '新报单逻辑';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        event(new XinbaodanzuihouluojiEvent());
        //
    }
}
