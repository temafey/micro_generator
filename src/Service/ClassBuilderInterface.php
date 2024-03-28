<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;

interface ClassBuilderInterface
{
    /**
     * Generate test.
     *
     * @param string $name
     * @param string $type
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     * @param string $layerPatternPath
     *
     * @return bool
     *
     * @throws InvalidClassTypeException
     */
    public function generate(
        string $domainName,
        string $layer,
        string $type,
        string $name,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ): bool;
}
