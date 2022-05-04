<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;

/**
 * Interface GeneratorInterface.
 */
interface GeneratorInterface
{
    public const UNIQUE_KEY_UUID = 'uuid';
    public const UNIQUE_KEY_PROCESS_UUID = 'process_uuid';
    public const UNIQUE_KEY_FIND_CRITERIA = 'find_criteria';
    public const UNIQUE_KEYS = [self::UNIQUE_KEY_PROCESS_UUID, self::UNIQUE_KEY_UUID];

    /**
     * Return full name of class that could be generated.
     * @throws InvalidClassTypeException
     */
    public function getFullClassName(): string;

    /**
     * Return source class path.
     */
    public function getSourceFile(): string;

    /**
     * Generates the code and writes it to a source file.
     *
     * @return bool
     */
    public function write(): bool;
}
