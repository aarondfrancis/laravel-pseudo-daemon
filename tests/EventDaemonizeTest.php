<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Resolute\PseudoDaemon\Tests;

use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Orchestra\Testbench\TestCase;
use Resolute\PseudoDaemon\PseudoDaemonServiceProvider;

class EventDaemonizeTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PseudoDaemonServiceProvider::class
        ];
    }

    /** @test */
    public function daemonize_macro_sets_appropriate_properties()
    {
        $mutex = new CacheEventMutex(cache());
        $event = (new Event($mutex, 'command'))->daemonize();

        $this->assertTrue($event->runInBackground);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertEquals('* * * * *', $event->expression);
    }
}