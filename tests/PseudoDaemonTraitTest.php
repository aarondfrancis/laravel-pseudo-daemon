<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Resolute\PseudoDaemon\Tests;

use Illuminate\Support\Carbon;
use Mockery;
use Orchestra\Testbench\TestCase;
use Resolute\PseudoDaemon\PseudoDaemonControl;
use Resolute\PseudoDaemon\Tests\Support\TestCommand;

class PseudoDaemonTraitTest extends TestCase
{
    /** @test */
    public function it_runs_once_during_tests()
    {
        $command = new TestCommand;

        $this->assertEquals(0, $command->pseudoDaemonTimesRun);

        $command->handle();

        $this->assertEquals(1, $command->pseudoDaemonTimesRun);
    }

    /** @test */
    public function it_stops_after_specified_number_of_runs()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 3,
            'pseudoDaemonSleepSeconds' => 0,
        ]);

        $command->handle();

        $this->assertEquals(3, $command->pseudoDaemonTimesRun);
    }

    /** @test */
    public function it_sleeps()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 2,
            'pseudoDaemonSleepSeconds' => 2,
        ]);

        $started = now();

        $command->handle();

        $finished = now();

        $this->assertEquals(2, $command->pseudoDaemonTimesRun);

        // Doesn't sleep on the first run, does sleep on the second.
        $this->assertEquals(2, $started->diffInSeconds($finished));
    }

    /** @test */
    public function sleeping_can_be_skipped()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 2,
            'pseudoDaemonSleepSeconds' => 30,
            'process' => PseudoDaemonControl::DONT_SLEEP,
        ]);

        $started = now();

        $command->handle();

        $finished = now();

        $this->assertEquals(2, $command->pseudoDaemonTimesRun);

        $this->assertEquals(0, $started->diffInSeconds($finished));
    }

    /** @test */
    public function daemon_can_be_killed_via_process_method()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 1000,
            'pseudoDaemonSleepSeconds' => 0,
            'process' => PseudoDaemonControl::STOP,
        ]);

        $command->handle();

        $this->assertEquals(1, $command->pseudoDaemonTimesRun);
    }

    /** @test */
    public function changing_data_stops_the_daemon()
    {
        $fakeData = 0;

        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 1000,
            'pseudoDaemonSleepSeconds' => 0,
        ]);

        $command->shouldReceive('restartWhenChanged')->andReturnUsing(function () use (&$fakeData) {
            $fakeData++;
            return $fakeData < 5 ? 'Less than five' : 'More than five';
        });

        $command->handle();

        $this->assertEquals(3, $command->pseudoDaemonTimesRun);
    }

    /** @test */
    public function it_stops_if_it_runs_out_of_time()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 1000,
            'pseudoDaemonSleepSeconds' => 0,
            'restartAfterMinutes' => 60
        ]);

        // Hook into the process method to change the time
        $command->shouldReceive('process')->andReturnUsing(function () {
            // Move time forward one minute
            Carbon::setTestNow(now()->addMinute());
        });

        // Clear the test `now`
        Carbon::setTestNow();

        $command->handle();

        $this->assertEquals(60, $command->pseudoDaemonTimesRun);
    }

    /** @test */
    public function before_run_is_called_once()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 10,
            'pseudoDaemonSleepSeconds' => 0,
        ]);

        $command->shouldReceive('beforePseudoDaemonRun')->once();

        $command->handle();
    }

    /** @test */
    public function before_shutdown_is_called_once()
    {
        $command = Mockery::mock(TestCommand::class)->makePartial();
        $command->shouldReceive([
            'restartAfterNumberOfTimesRun' => 10,
            'pseudoDaemonSleepSeconds' => 0,
        ]);

        $command->shouldReceive('beforePseudoDaemonShutdown')->once();

        $command->handle();
    }
}