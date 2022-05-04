<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

/**
 * Interface GeneratorInterface.
 */
interface TemplateInterface
{
    /**
     * Sets the template file.
     *
     * @throws Exception
     */
    public function setFile(string $file): void;

    /**
     * Sets one or more template variables.
     */
    public function setVar(array $values, bool $merge = true): void;

    /**
     * Renders the template and returns the result.
     */
    public function render(): string;

    /**
     * Renders the template and writes the result to a file.
     */
    public function renderTo(string $target): void;
}
