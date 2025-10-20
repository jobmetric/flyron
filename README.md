[contributors-shield]: https://img.shields.io/github/contributors/jobmetric/flyron.svg?style=for-the-badge
[contributors-url]: https://github.com/jobmetric/flyron/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/jobmetric/flyron.svg?style=for-the-badge&label=Fork
[forks-url]: https://github.com/jobmetric/flyron/network/members
[stars-shield]: https://img.shields.io/github/stars/jobmetric/flyron.svg?style=for-the-badge
[stars-url]: https://github.com/jobmetric/flyron/stargazers
[license-shield]: https://img.shields.io/github/license/jobmetric/flyron.svg?style=for-the-badge
[license-url]: https://github.com/jobmetric/flyron/blob/master/LICENCE.md
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-blue.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/majidmohammadian

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

# Flyron

Flyron is a PHP package for `asynchronous programming` using `Fibers` and `process-based` concurrency, specially designed for Laravel applications.

## Install via composer

Run the following command to pull in the latest version:

```bash
composer require jobmetric/flyron
```

### Publish the config
Copy the `config` file from `vendor/jobmetric/flyron/config/config.php` to `config` folder of your Laravel application and rename it to `flyron.php`

Run the following command to publish the package config file:

```bash
php artisan vendor:publish --provider="JobMetric\Flyron\FlyronServiceProvider" --tag="flyron-config"
```

You should now have a `config/flyron.php` file that allows you to configure the basics of this package.

## Why Flyron? (Philosophy)

- Build `async` flows without losing code readability. Fibers + Promises keep your code straightforward while managing waiting (I/O, network, timers).
- Offload heavy or isolated work to safe background `processes` (AsyncProcess) with signed payloads, optional encryption, and PID tracking.
- Not every async need fits queues. Sometimes you want to execute a `Closure` right now, in a separate process, without defining a new Job class. Flyron covers that gap.

Key choices:
- Use `Async + Promise` for cooperative, in-process concurrency (I/O-bound, step-wise logic).
- Use `AsyncProcess` for isolated or long-running tasks (heavy CPU, independent lifecycle, separation of failure).

--------------------------------------------------------------------------------

## Quick Start

```php
use JobMetric\Flyron\Facades\Async;

$promise = Async::run(fn (int $x) => $x + 1, [41]);
$value = $promise->run(); // 42
```

```php
use JobMetric\Flyron\Facades\AsyncProcess;

$pid = AsyncProcess::run(function () {
    file_put_contents(storage_path('app/report.txt'), 'done');
}, [], [
    'label' => 'make-report',
    'timeout' => 30,
]);
```

Important:
- Set a valid `APP_KEY` (payloads are signed). For extra security, enable `encryption` in `config/flyron.php`.
- Manage PID and payload folders via built-in console commands and scheduling.

--------------------------------------------------------------------------------

## Async and Promise

Async runs your callback inside a `Fiber` and returns a `Promise`. Promise supports `then`, `map`, `tap`, `recover`, `finally`, `cancel`, `withTimeout`.

```php
use JobMetric\Flyron\Concurrency\Promise;

$result = Promise::from(fn () => 10)
    ->tap(fn ($v) => info('Tap: '.$v))
    ->map(fn ($v) => $v + 5)
    ->then(fn ($v) => Promise::from(fn () => $v * 2))
    ->finally(fn () => info('Done'))
    ->run(); // 30
```

Timeout and cooperative cancellation:

```php
use JobMetric\Flyron\Facades\Async;
use JobMetric\Flyron\Concurrency\CancellationToken;
use RuntimeException;

$token = new CancellationToken();

$p = Async::run(function () use ($token) {
    for ($i = 0; $i < 10_000; $i++) {
        \JobMetric\Flyron\Async::checkpoint($token, 'Operation cancelled');
    }
    return 'ok';
}, [], 200, $token);

$token->cancel();

try {
    $p->run();
} catch (RuntimeException $e) {
    // timed out or cancelled
}
```

Helpers:
- `Async::delay(int $ms)` — cooperative sleep.
- `Async::checkpoint(?CancellationToken $token, string $message = 'Operation cancelled.')` — throw if cancelled.

--------------------------------------------------------------------------------

## Await Utilities

```php
use JobMetric\Flyron\Concurrency\Await;
use JobMetric\Flyron\Concurrency\Promise;

$values = Await::all([
    Promise::from(fn () => 1),
    Promise::from(fn () => 2),
]); // [1, 2]

$first = Await::race([
    Promise::from(function () { usleep(10_000); return 'late'; }),
    Promise::from(fn () => 'early'),
]); // 'early'

$one = Await::any([
    Promise::from(fn () => throw new \RuntimeException('x')),
    Promise::from(fn () => 'ok'),
]); // 'ok'

$settled = Await::allSettled([
    Promise::from(fn () => 7),
    Promise::from(fn () => throw new \RuntimeException('bad')),
]);

$value = Await::until(function () {
    static $i = 0; $i++;
    return $i >= 3 ? 'ready' : null;
}, 200, 10);
```

--------------------------------------------------------------------------------

## AsyncProcess (Background Processes)

Run a serialized `Closure` in a separate PHP process. Payloads are `HMAC`-signed with `APP_KEY` (and can be encrypted). A PID file is written so you can list/clean/kill processes.

```php
use JobMetric\Flyron\Facades\AsyncProcess;

$pid = AsyncProcess::run(function () {
    file_put_contents(storage_path('app/report.txt'), 'done');
}, [], [
    'label' => 'make-report',
    'cwd' => base_path(),
    'env' => ['MY_FLAG' => '1'],
    'timeout' => 30,         // seconds (Symfony Process)
    'idle_timeout' => null,  // optional
    'disable_output' => true,
]);
```

Options:
- `cwd`, `env`, `timeout`, `idle_timeout`, `disable_output`, `label`

Security and stability:
- Payloads are signed with `APP_KEY` (`HMAC-SHA256`). If tampered, execution fails.
- Optional encryption (`aes-256-gcm`) is supported.
- Payload path is restricted to `storage/flyron/payloads`.

Concurrency throttle:
- `flyron.process.max_concurrency` — limit concurrent background processes (`0` means unlimited)
- `flyron.process.throttle_mode` — `reject` or `wait`
- `flyron.process.throttle_wait_max_seconds`, `flyron.process.throttle_wait_interval_ms`

--------------------------------------------------------------------------------

## Helpers and Facades

- `async(callable $callback, array $args = [], ?int $timeout = null, ?CancellationToken $token = null): Promise`
  - Same as `Async::run(...)`

- `async_process(callable $callback, array $args = [], array $options = []): ?int`
  - Same as `AsyncProcess::run(...)`

Facades:
- `Async` → Fiber/Promise runtime
- `AsyncProcess` → background process launcher

--------------------------------------------------------------------------------

## Console Commands

- `php artisan flyron:process-list` — list tracked processes with status.
- `php artisan flyron:process-clean {--payloads}` — remove dead PID files; with `--payloads`, remove old payloads by TTL.
- `php artisan flyron:process-kill {pid}` — kill a Flyron-managed process (PID file must exist).
- `php artisan flyron:optimize` — clean stale PID files (maintenance).
- `php artisan flyron:exec {payload_path}` — internal; used by AsyncProcess.

--------------------------------------------------------------------------------

## Configuration (Highlights)

```php
return [
  'php_path' => env('FLYRON_PHP_PATH', PHP_BINARY),
  'artisan_path' => env('FLYRON_ARTISAN_PATH', base_path('artisan')),

  'schedule' => [
    'enabled' => env('FLYRON_SCHEDULE_ENABLED', true),
    'environments' => env('FLYRON_SCHEDULE_ENVIRONMENTS') ? explode(',', env('FLYRON_SCHEDULE_ENVIRONMENTS')) : [],
    'process_clean' => [
      'enabled' => env('FLYRON_SCHEDULE_PROCESS_CLEAN', true),
      'frequency' => env('FLYRON_SCHEDULE_PROCESS_CLEAN_FREQUENCY', 'hourly'),
      'payloads' => env('FLYRON_SCHEDULE_PROCESS_CLEAN_PAYLOADS', true),
    ],
    'process_optimize' => [
      'enabled' => env('FLYRON_SCHEDULE_PROCESS_OPTIMIZE', false),
      'frequency' => env('FLYRON_SCHEDULE_PROCESS_OPTIMIZE_FREQUENCY', 'weekly'),
    ],
  ],

  'process' => [
    'encryption_enabled' => env('FLYRON_PROCESS_ENCRYPTION', false),
    'encryption_cipher' => env('FLYRON_PROCESS_CIPHER', 'aes-256-gcm'),
    'payload_ttl_seconds' => env('FLYRON_PROCESS_PAYLOAD_TTL', 86400),
    'max_concurrency' => env('FLYRON_PROCESS_MAX_CONCURRENCY', 0),
    'throttle_mode' => env('FLYRON_PROCESS_THROTTLE_MODE', 'reject'),
    'throttle_wait_max_seconds' => env('FLYRON_PROCESS_THROTTLE_WAIT_MAX', 30),
    'throttle_wait_interval_ms' => env('FLYRON_PROCESS_THROTTLE_WAIT_INTERVAL', 200),
  ],
];
```

Scheduling:
- You can enable periodic `process-clean` and `optimize` with flexible frequencies (method strings like `daily`, `hourlyAt:15`, or a raw cron expression).

--------------------------------------------------------------------------------

## Best Practices

- Offload CPU-heavy work to `AsyncProcess` or queues. Use `Promise/Fiber` for I/O-bound or step-wise operations.
- Use `Async::checkpoint($token)` inside long loops to make cancellation responsive.
- Ensure a valid `APP_KEY`; in production, consider enabling payload encryption.
- Schedule `flyron:process-clean` to keep PID/payload folders tidy.

--------------------------------------------------------------------------------

## Testing

This package includes unit and feature tests covering: `Promise/Await/Async`, Traits, Facades, Helpers, ServiceProvider (bindings and scheduling), and Console Commands.

Run tests:

```bash
php vendor/bin/phpunit
```

--------------------------------------------------------------------------------

## License

The MIT License (MIT). Please see [License File](https://github.com/jobmetric/flyron/blob/master/README.md) for more information.
