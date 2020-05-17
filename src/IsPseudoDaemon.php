<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Resolute\PseudoDaemon;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait IsPseudoDaemon
{
    /**
     * @var Carbon
     */
    public $pseudoDaemonStartedAt;

    /**
     * @var mixed
     */
    public $pseudoDaemonInitialData;

    /**
     * @var int
     */
    public $pseudoDaemonTimesRun = 0;

    public function runAsPseudoDaemon()
    {
        $this->pseudoDaemonStartedAt = now();

        // Don't sleep on the first run.
        $skipSleeping = true;

        $this->beforePseudoDaemonRun();

        while ($this->pseudoDaemonShouldRun()) {
            $this->pseudoDaemonSleep($skipSleeping);

            $processed = $this->process();

            $this->pseudoDaemonTimesRun++;

            if ($processed === PseudoDaemonControl::STOP) {
                break;
            }

            // Sleeping is the default, so we'll just check to see if the
            // developer has explicitly requested that we *not* sleep.
            $skipSleeping = $processed === PseudoDaemonControl::DONT_SLEEP;
        }

        $this->beforePseudoDaemonShutdown();
    }

    /**
     * This is the main method that will be kept alive.
     *
     * @return null|int
     */
    abstract public function process();

    public function beforePseudoDaemonRun()
    {
        //
    }

    public function beforePseudoDaemonShutdown()
    {
        //
    }

    public function pseudoDaemonSleep($skip)
    {
        if ($skip) {
            return;
        }

        sleep($this->pseudoDaemonSleepSeconds());
    }

    public function pseudoDaemonSleepSeconds()
    {
        return 7;
    }

    public function pseudoDaemonShouldRun()
    {
        $stopAt = $this->pseudoDaemonStartedAt->copy()->addMinutes($this->restartAfterMinutes());

        if (now()->isAfter($stopAt)) {
            return false;
        }

        if ($this->pseudoDaemonTimesRun >= $this->restartAfterNumberOfTimesRun()) {
            return false;
        }

        // Store the data on our first run.
        if ($this->pseudoDaemonInitialData === null) {
            $this->pseudoDaemonInitialData = $this->restartWhenChanged();
        }

        // Make sure that the restartWhenChanged data hasn't changed.
        return $this->pseudoDaemonInitialData === $this->restartWhenChanged();
    }

    public function restartAfterNumberOfTimesRun()
    {
        return app()->environment('production') ? 1000 : 1;
    }

    public function restartAfterMinutes()
    {
        return 60;
    }

    public function restartWhenChanged()
    {
        // If we're on Forge, then restart every time there's a new build.
        return $this->currentForgeEnvoyerRelease();
    }

    public function currentForgeEnvoyerRelease()
    {
        $pwd = trim(shell_exec('pwd'));

        // If the string matches the pattern "/home/forge/[example.com]/current" then
        // we're running on Laravel Forge. If that's the case, then we'll see what
        // the real directory is, meaning a new deploy will trigger a restart.
        if (Str::startsWith($pwd, '/home/forge/') && Str::endsWith($pwd, '/current')) {
            return shell_exec('readlink ' . escapeshellarg($pwd));
        }
    }

}