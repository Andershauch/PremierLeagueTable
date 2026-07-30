<?php

/**
 * Invokes a private/protected method for testing without changing the
 * production class's visibility. Standard, non-invasive PHP testing technique.
 */
function invoke_private_method(object $object, string $methodName, array $args = [])
{
    $reflection = new ReflectionMethod($object, $methodName);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}
