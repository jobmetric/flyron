<?php

namespace JobMetric\Flyron\Console\Commands;

use Illuminate\Console\Command;
use JobMetric\PackageCore\Commands\ConsoleTools;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

class FlyronExec extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flyron:exec {payload_path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute a signed payload that contains a serialized closure';

    /**
     * Execute the console command.
     *
     * Reads the payload file, verifies its HMAC using APP_KEY, then unserializes
     * the contained SerializableClosure and executes it. Cleans up payload and PID files.
     *
     * @return int
     */
    public function handle(): int
    {
        $payloadPath = (string)$this->argument('payload_path');

        try {
            if (! is_file($payloadPath)) {
                $this->message('Payload file not found: '.$payloadPath, 'error');

                return self::FAILURE;
            }

            // Restrict path under storage/flyron/payloads
            $real = realpath($payloadPath) ?: $payloadPath;
            $base = realpath(storage_path('flyron/payloads')) ?: storage_path('flyron/payloads');
            if ($real === false || strncmp($real, $base, strlen($base)) !== 0) {
                $this->message('Invalid payload path.', 'error');

                return self::FAILURE;
            }

            $data = json_decode((string)file_get_contents($payloadPath), true);
            if (! is_array($data) || ! isset($data['c'], $data['h'])) {
                $this->message('Invalid payload structure.', 'error');

                return self::FAILURE;
            }

            $procCfg = (array)config('flyron.process', []);
            $encEnabled = (bool)($data['e'] ?? false);
            $cipher = (string)($data['y'] ?? ($procCfg['encryption_cipher'] ?? 'aes-256-gcm'));

            $raw = base64_decode((string)$data['c'], true);
            if ($raw === false) {
                $this->message('Failed to decode payload content.', 'error');

                return self::FAILURE;
            }

            if ($encEnabled) {
                $iv = base64_decode((string)($data['i'] ?? ''), true) ?: '';
                $tag = base64_decode((string)($data['g'] ?? ''), true) ?: '';
                $secret = $this->getAppKey();
                $dec = openssl_decrypt($raw, $cipher, $secret, OPENSSL_RAW_DATA, $iv, $tag);
                if ($dec === false) {
                    $this->message('Failed to decrypt payload.', 'error');

                    return self::FAILURE;
                }
                $serialized = $dec;
            } else {
                $serialized = $raw;
            }

            $secret = $this->getAppKey();
            $expected = hash_hmac('sha256', $serialized, $secret);
            if (! hash_equals($expected, (string)$data['h'])) {
                $this->message('Payload HMAC verification failed.', 'error');

                return self::FAILURE;
            }

            $closure = unserialize($serialized, ['allowed_classes' => true]);
            if ($closure instanceof SerializableClosure) {
                $closure = $closure->getClosure();
            }

            if (! is_callable($closure)) {
                $this->message('Payload did not contain a callable closure.', 'error');

                return self::FAILURE;
            }

            ob_start();
            call_user_func($closure);
            $buffer = ob_get_clean();
            if (! empty($buffer)) {
                $pidFile = storage_path('flyron/pids/'.getmypid().'.pid');
                if (is_file($pidFile)) {
                    $meta = json_decode((string)file_get_contents($pidFile), true);
                    if (is_array($meta)) {
                        $logPath = $meta['payload'].'.log';
                        @file_put_contents($logPath, $buffer, FILE_APPEND);
                    }
                }
            }
            $this->message('Closure executed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->message('Failed to execute closure: '.$e->getMessage(), 'error');

            return self::FAILURE;
        } finally {
            // Cleanup payload file
            if (isset($payloadPath) && is_file($payloadPath)) {
                @unlink($payloadPath);
            }
            // Cleanup PID file for this process
            $pidFile = storage_path('flyron/pids/'.getmypid().'.pid');
            if (is_file($pidFile)) {
                @unlink($pidFile);
            }
        }
    }

    /**
     * Get the APP_KEY as binary secret for HMAC verification.
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
