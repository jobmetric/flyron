<?php

namespace JobMetric\Flyron\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Class Await
 *
 * Provides static utility methods to synchronously wait on asynchronous Promises.
 * This class simplifies working with promises by providing methods like one, all, race, any,
 * and utility methods such as delay and until.
 *
 * @package JobMetric\Flyron
 */
class Await
{
    /**
     * Wait for a single promise to resolve and return the result.
     *
     * @template T
     * @param Promise<T> $promise The promise to wait on.
     *
     * @return T The resolved value of the promise.
     * @throws Throwable Throws if the promise rejects or errors.
     */
    public static function one(Promise $promise): mixed
    {
        return $promise->run();
    }

    /**
     * Wait for all promises to resolve and return their results as an array.
     *
     * @template T
     * @param Promise<T>[] $promises Array of Promise instances to wait on.
     *
     * @return T[] Array of resolved values indexed by input keys.
     * @throws Throwable Throws if any of the promises rejects or errors.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function all(array $promises): array
    {
        return Promise::all($promises);
    }

    /**
     * Wait until the first promise resolves (fulfills or rejects) and return its result.
     *
     * @template T
     * @param Promise<T>[] $promises Array of Promise instances to race.
     *
     * @return T The resolved value of the first settled promise.
     * @throws Throwable Throws if the first settled promise rejects or errors.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function race(array $promises): mixed
    {
        return Promise::race($promises);
    }

    /**
     * Wait until any one of the promises resolves successfully (first fulfilled).
     *
     * If all promises reject, throws a RuntimeException.
     *
     * @template T
     * @param Promise<T>[] $promises Array of Promise instances.
     *
     * @return T The resolved value of the first fulfilled promise.
     * @throws Throwable Throws if all promises reject.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function any(array $promises): mixed
    {
        Promise::validatePromises($promises);

        $errors = [];

        while (true) {
            foreach ($promises as $promise) {
                if (!$promise->isPending()) {
                    try {
                        return $promise->run();
                    } catch (Throwable $e) {
                        $errors[] = $e;
                    }
                }
            }

            if (count($errors) === count($promises)) {
                throw new RuntimeException("All promises were rejected.");
            }

            usleep(1000);
        }
    }

    /**
     * Delay execution for a specified number of milliseconds.
     *
     * @param int $ms Delay duration in milliseconds.
     * @return void
     */
    public static function delay(int $ms): void
    {
        usleep($ms * 1000);
    }

    /**
     * Wait until a given condition returns a truthy value or a timeout occurs.
     *
     * Checks the callable repeatedly every $intervalMs milliseconds until
     * the condition returns a truthy value or the timeout is reached.
     *
     * @param callable(): mixed $condition A callable returning a truthy value to stop waiting.
     * @param int $timeoutMs Maximum wait time in milliseconds before throwing.
     * @param int $intervalMs Polling interval in milliseconds.
     *
     * @return mixed The truthy value returned by the condition.
     * @throws RuntimeException If the timeout is reached before condition returns truthy.
     */
    public static function until(callable $condition, int $timeoutMs = 10000, int $intervalMs = 100): mixed
    {
        $start = microtime(true);

        while (true) {
            $result = $condition();

            if ($result) {
                return $result;
            }

            if ((microtime(true) - $start) * 1000 > $timeoutMs) {
                throw new RuntimeException("Timeout waiting for condition.");
            }

            usleep($intervalMs * 1000);
        }
    }
}
