<?php

namespace JobMetric\Flyron;

use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use RuntimeException;
use Symfony\Component\Process\Process;

class AsyncProcess
{
    /**
     * Dispatch a callable to be run in a separate OS process.
     *
     * Security and robustness:
     * - Uses a file-based payload with HMAC (APP_KEY) instead of passing serialized content as CLI argument.
     * - Ensures PID/payload directories exist.
     * - Spawns process via Symfony Process array API to avoid shell-quoting issues.
     *
     * Options:
     * - cwd:     string|null  Working directory for the process
     * - env:     array|null   Extra environment variables for the process
     * - timeout: float|null   Process timeout in seconds (Symfony Process)
     * - label:   string|null  Optional label saved in PID metadata
     *
     * @template T
     * @param callable(mixed ...): T                                                                $callback The callback to execute in a separate process.
     * @param array                                                                                 $args     Optional arguments passed to the callback.
     * @param array{cwd?: string|null, env?: array|null, timeout?: float|null, label?: string|null} $options
     *
     * @return int|null PID of the spawned process or null if unavailable.
     * @throws PhpVersionNotSupportedException
     */
    public function run(callable $callback, array $args = [], array $options = []): ?int
    {
        $phpPath = (string)config('flyron.php_path', PHP_BINARY);
        $artisan = (string)config('flyron.artisan_path', base_path('artisan'));

        if (! is_string($phpPath) || $phpPath === '' || ! is_file($phpPath)) {
            throw new RuntimeException("Invalid PHP binary path: {$phpPath}");
        }

        if (! file_exists($artisan)) {
            throw new RuntimeException("Artisan file not found at: {$artisan}");
        }

        $this->ensureDirectory(storage_path('flyron/pids'));
        $this->ensureDirectory(storage_path('flyron/payloads'));

        // Optional throttle on max concurrency
        $procCfg = (array)config('flyron.process', []);
        $maxConc = (int)($procCfg['max_concurrency'] ?? 0);
        if ($maxConc > 0) {
            $mode = (string)($procCfg['throttle_mode'] ?? 'reject');
            $waitMax = (int)($procCfg['throttle_wait_max_seconds'] ?? 30);
            $waitInt = (int)($procCfg['throttle_wait_interval_ms'] ?? 200);

            $startTime = microtime(true);
            $check = function (): bool {
                $pids = glob(storage_path('flyron/pids/*.pid'));

                return count($pids) < (int)config('flyron.process.max_concurrency');
            };

            if ($mode === 'wait') {
                while (! $check()) {
                    if ((microtime(true) - $startTime) > $waitMax) {
                        throw new RuntimeException('AsyncProcess throttled by max_concurrency (wait timeout).');
                    }
                    usleep(max(1, $waitInt) * 1000);
                }
            } else {
                if (! $check()) {
                    throw new RuntimeException('AsyncProcess throttled by max_concurrency.');
                }
            }
        }

        // Serialize the closure using laravel/serializable-closure
        $serialized = serialize(new SerializableClosure(fn () => $callback(...$args)));

        // Build signed (and optionally encrypted) payload
        $secret = $this->getAppKey();
        if ($secret === '') {
            throw new RuntimeException('APP_KEY is not configured; cannot sign payload.');
        }

        $encEnabled = (bool)(($procCfg['encryption_enabled'] ?? false));
        $cipher = (string)($procCfg['encryption_cipher'] ?? 'aes-256-gcm');

        $content = base64_encode($serialized);
        $iv = null;
        $tag = null;
        if ($encEnabled) {
            $iv = random_bytes(12);
            $enc = openssl_encrypt($serialized, $cipher, $secret, OPENSSL_RAW_DATA, $iv, $tag);
            if ($enc === false || $tag === null) {
                throw new RuntimeException('Failed to encrypt payload.');
            }
            $content = base64_encode($enc);
        }

        $payload = ['c' => $content, 'h' => hash_hmac('sha256', $serialized, $secret), 't' => time(), 'label' => $options['label'] ?? null,];
        if ($encEnabled) {
            $payload['e'] = true;
            $payload['i'] = base64_encode($iv);
            $payload['g'] = base64_encode($tag);
            $payload['y'] = $cipher;
        }

        $payloadId = uniqid('flyron_', true);
        $payloadPath = storage_path("flyron/payloads/{$payloadId}.json");
        file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Build process command using array form
        $process = new Process([$phpPath, $artisan, 'flyron:exec', $payloadPath,], $options['cwd'] ?? null, $options['env'] ?? null, null, $options['timeout'] ?? null);

        if (array_key_exists('timeout', $options) && $options['timeout'] !== null) {
            $process->setTimeout((float)$options['timeout']);
        }
        if (array_key_exists('idle_timeout', $options) && $options['idle_timeout'] !== null) {
            $process->setIdleTimeout((float)$options['idle_timeout']);
        }
        $disableOutput = $options['disable_output'] ?? true;
        if ($disableOutput) {
            $process->disableOutput();
        }
        $process->start();

        $pid = $process->getPid();

        if ($pid) {
            $meta = ['pid' => $pid, 'cmd' => [$phpPath, $artisan, 'flyron:exec', $payloadPath], 'payload' => $payloadPath, 'label' => $options['label'] ?? null, 'created_at' => date('c'), 'uuid' => bin2hex(random_bytes(8)),];
            file_put_contents(storage_path("flyron/pids/{$pid}.pid"), json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $pid;
    }

    /**
     * Ensure directory exists.
     *
     * @param string $dir
     *
     * @return void
     */
    protected function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * Get the APP_KEY as binary secret for HMAC.
     *
     * @return string
     */
    protected function getAppKey(): string
    {
        $key = (string)config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
