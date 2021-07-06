<?php

declare(strict_types=1);


namespace MicroModule\MicroserviceGenerator\Generator\Preprocessor;

use ReflectionClass;
use ReflectionMethod;

/**
 * Interface PreprocessorInterface.
 */
interface PreprocessorInterface
{
    /**
     * Exec preprocessor logic.
     *
     * @param ReflectionClass $reflectionClass
     * @param ReflectionMethod $reflectionMethod
     * @param string $testMethodName
     * @param string|null $testMethodBody
     */
    public function process(ReflectionClass $reflectionClass, ReflectionMethod $reflectionMethod, string &$testMethodName, ?string &$testMethodBody): void;
}
