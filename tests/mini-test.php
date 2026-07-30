<?php

/**
 * Dependency-free assertion/runner used by tests/run-unit-tests.php and the
 * live smoke test. No Composer, no PHPUnit — just `php` on the CLI, so it can
 * run anywhere (local machine or CI) with zero install step.
 */
class MiniTest
{
    private static string $currentSuite = '';
    private static int $passed = 0;
    private static int $skipped = 0;
    private static array $failures = [];

    public static function suite(string $name, callable $fn): void
    {
        self::$currentSuite = $name;
        echo "\n=== {$name} ===\n";

        try {
            $fn();
        } catch (Throwable $e) {
            self::fail('Uncaught exception: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
        }
    }

    public static function assertTrue(bool $condition, string $message): void
    {
        if ($condition) {
            self::pass($message);
            return;
        }

        self::fail($message . ' (expected true, got false)');
    }

    public static function assertEquals($expected, $actual, string $message): void
    {
        if ($expected == $actual) { // phpcs:ignore -- loose compare is intentional here
            self::pass($message);
            return;
        }

        self::fail($message . ' (expected ' . self::describe($expected) . ', got ' . self::describe($actual) . ')');
    }

    public static function assertSame($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            self::pass($message);
            return;
        }

        self::fail($message . ' (expected ' . self::describe($expected) . ', got ' . self::describe($actual) . ')');
    }

    public static function assertGreaterThan($threshold, $actual, string $message): void
    {
        if ($actual > $threshold) {
            self::pass($message);
            return;
        }

        self::fail($message . ' (expected > ' . self::describe($threshold) . ', got ' . self::describe($actual) . ')');
    }

    public static function skip(string $message): void
    {
        self::$skipped++;
        echo "  SKIP: {$message}\n";
    }

    private static function pass(string $message): void
    {
        self::$passed++;
        echo "  PASS: {$message}\n";
    }

    private static function fail(string $message): void
    {
        $labelled = '[' . self::$currentSuite . '] ' . $message;
        self::$failures[] = $labelled;
        echo "  FAIL: {$message}\n";
    }

    private static function describe($value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    public static function summaryAndExit(): void
    {
        $failedCount = count(self::$failures);
        echo "\n---\n";
        echo self::$passed . " passed, {$failedCount} failed, " . self::$skipped . " skipped.\n";

        if (self::$failures !== []) {
            echo "\nFailures:\n";
            foreach (self::$failures as $failure) {
                echo " - {$failure}\n";
            }

            exit(1);
        }

        exit(0);
    }
}
