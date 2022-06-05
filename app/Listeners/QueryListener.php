<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Log;

class QueryListener
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
     * @param object $event
     * @return void
     */
    public function handle($event)
    {
        if (env('SQL_LOG', true)) {
            $sql = str_replace("?", "'%s'", $event->sql);
            $log = vsprintf($sql, $event->bindings);
            Log::channel('sql')->info($log);
        }
    }

}
