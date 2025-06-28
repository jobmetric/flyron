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
    protected $signature = 'flyron:exec {closure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute a serialized closure asynchronously';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $serialized = $this->argument('closure');

        try {
            $closure = unserialize($serialized);

            if ($closure instanceof SerializableClosure) {
                $closure = $closure->getClosure();
            }

            if (is_callable($closure)) {
                call_user_func($closure);
                $this->message('Closure executed successfully.', 'info');

                return self::SUCCESS;
            } else {
                $this->message('Provided argument is not a callable closure.', 'error');

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->message('Failed to execute closure: ' . $e->getMessage(), 'error');

            return self::FAILURE;
        }
    }
}
