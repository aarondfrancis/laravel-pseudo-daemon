<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Resolute\PseudoDaemon;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\ServiceProvider;

class PseudoDaemonServiceProvider extends ServiceProvider
{
    public function register()
    {
        Event::macro('daemonize', function () {
            if ($this instanceof CallbackEvent) {
                throw new \Exception('Cannot daemonize a CallbackEvent.');
            }

            return $this->everyMinute()->runInBackground()->withoutOverlapping();
        });
    }
}