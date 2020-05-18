# Laravel Pseudo-Daemons

[![Build Status](https://travis-ci.org/aarondfrancis/laravel-pseudo-daemon.svg?branch=master)](https://travis-ci.org/aarondfrancis/laravel-pseudo-daemon)

A Laravel package to mimic daemons via scheduled commands without having to change server configuration. 

> For more information / rationale, see https://aaronfrancis.com/2020/laravel-pseudo-daemons.

# Installation

You can install the package via composer:

`composer require resolute/laravel-pseudo-daemon`

# Basic Usage

Add the `IsPseudoDaemon` trait to any of your Laravel Commands and call `runAsPseudoDaemon` from the `handle` method.

```php
class TestCommand extends Command
{
    use IsPseudoDaemon;

    public function handle()
    {
        $this->runAsPseudoDaemon();
    }

    /**
     * This is the main method that will be kept alive.
     */
    public function process()
    {
        // All of your processing...
    }
}
``` 

Then, in your `Console\Kernel`, run your command every minute, in the background, without overlapping.

```php
// Kernel.php

$schedule->command('test')->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();
```

The `process` method will be kept alive for as long as you want, all controlled by your code without any Supervisor configuration, and without having to change your deploy scripts to kill it.


# Stopping the Daemon

Obviously there are going to be times you need to kill your daemon so that the scheduler can restart it. The most obvious time is when deploying new code, but there are plenty of other reasons to kill it off, and several ways to do so.

## Restart After Number Of Times Run

If you'd like to stop the daemon after it runs a certain number of times, you can override the `restartAfterNumberOfTimesRun()` method. By default, it returns `1000` in production and `1` otherwise.

## Restart After Minutes

To set a maximum runtime in minutes, override `restartAfterMinutes()`. By default the daemons all run for 60 minutes.

## Restart When Something Arbitrary Changes

You'll want to kill your daemons when you deploy new code, which is easy enough with the `restartWhenChanged()` method. You can return any data you want from this method, and if it ever changes the daemon will stop.

### Forge + Envoyer

If you're using on Laravel Forge with Envoyer, the trait will automatically handle stopping itself whenever you deploy fresh code. You don't have to do a single thing! The trait will read the real path of the `current` symlink that Envoyer creates. Anytime that changes the daemon will stop.

### Other Hosting

If you're not on Forge with Envoyer, you can extend the `restartWhenChanged()` method and return whatever you want. You can read a git hash, a build time, or do anything else. Anytime we detect that the data is different, the loop breaks.

Here's an example that reads the current git hash:

```php
public function restartWhenChanged()
{
    // Restart whenever the git hash changes.
    // https://stackoverflow.com/a/949391/1408651
    return shell_exec('git rev-parse HEAD');
}
```

## Stopping Whenever You Want

If you'd like to initiate a stop from inside your `process` method, you may return `PseudoDaemonControl::STOP` and the daemon will not run any more iterations.

# Sleeping

So that your daemon doesn't run thousands of times per minute when there is nothing to do, we default to sleeping 7 seconds between iterations. If you'd like to change that amount you may override `pseudoDaemonSleepSeconds()`.

If you'd like to explicitly _not_ sleep on a certain iteration, you may return `PseudoDaemonControl::DONT_SLEEP` from your `process` method. 

# Running Code Before / After
If you'd like to do some setup and/or cleanup outside of the main loop, `beforePseudoDaemonRun()` and `beforePseudoDaemonShutdown()` are both available to you.