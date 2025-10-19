<?php

namespace JobMetric\Flyron\Concurrency;

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
     * Wait for a single promise to settle and return its value.
     *
     * @template T
     * @param Promise<T> $promise The promise to wait on.
     *
     * @return T The resolved value of the promise.
     * @throws Throwable If the promise rejects or errors.
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
     * @return array<string|int, T> Resolved values indexed by input keys.
     * @throws Throwable If any of the promises rejects or errors.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function all(array $promises): array
    {
        return Promise::all($promises);
    }

    /**
     * Resolve to the first settled promise (deterministic, sequential evaluation).
     *
     * @template T
     * @param Promise<T>[] $promises Array of Promise instances to race.
     *
     * @return T The resolved value of the first settled promise.
     * @throws Throwable If the first settled promise rejects or errors.
     */
    public static function race(array $promises): mixed
    {
        return Promise::race($promises);
    }

    /**
     * Return the first fulfilled value among the given promises (sequential evaluation).
     *
     * If all promises reject, throws a RuntimeException.
     *
     * @template T
     * @param Promise<T>[] $promises Array of Promise instances.
     *
     * @return T The resolved value of the first fulfilled promise.
     * @throws Throwable If all promises reject.
     * @throws InvalidArgumentException If any element is not a Promise instance.
     */
    public static function any(array $promises): mixed
    {
        Promise::validatePromises($promises);

        $errors = [];

        // Eager start to simulate concurrency
        foreach ($promises as $p) {
            $p->eagerStart();
        }

        // If any already settled successfully, return its value
        foreach ($promises as $promise) {
            if (! $promise->isPending() && $promise->isFulfilled()) {
                return $promise->getResult();
            }
        }

        // Fallback: run sequentially
        foreach ($promises as $promise) {
            try {
                return $promise->run();
            } catch (Throwable $e) {
                $errors[] = $e;
            }
        }

        if (count($errors) === count($promises)) {
            throw new RuntimeException("All promises were rejected.");
        }

        throw new RuntimeException("Unexpected state in Await::any");
    }

    /**
     * Wait for all promises to settle and return their outcomes.
     *
     * @param Promise[] $promises
     *
     * @return array<string|int, array{status: string, value?: mixed, reason?: Throwable}>
     */
    public static function allSettled(array $promises): array
    {
        return Promise::allSettled($promises);
    }

    /**
     * Delay execution for a specified number of milliseconds.
     *
     * Cooperative pause that yields control for approximately the given time.
     *
     * @param int $ms Delay duration in milliseconds.
     *
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
     * @param callable(): mixed $condition  A callable returning a truthy value to stop waiting.
     * @param int               $timeoutMs  Maximum wait time in milliseconds before throwing.
     * @param int               $intervalMs Polling interval in milliseconds.
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
