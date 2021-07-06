<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

use Exception;
use RuntimeException;

/**
 * Generator for base class skeletons from classes.
 *
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 */
class BaseGenerator extends AbstractGenerator
{
    /**
     * Constructor.
     *
     * @param string $className
     * @param string $sourceFile
     * @param string $baseNamespace
     *
     * @throws RuntimeException
     */
    public function __construct(string $className = '', string $sourceFile = '', string $baseNamespace = '')
    {
        $this->type = $baseNamespace;
        $this->className = $this->parseFullyQualifiedClassName($className);
        $this->sourceFile = str_replace(
            $this->className['fullyQualifiedClassName'],
            $this->className['className'],
            $sourceFile
        );
    }

    /**
     * Generate base test class.
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function generate(): ?string
    {
        $classTemplate = new Template(
            sprintf(
                '%s%stemplate%sBaseClass.tpl',
                __DIR__,
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR
            )
        );

        $classTemplate->setVar(
            [
                'namespace' => trim($this->className['namespace'], '\\'),
                'testClassName' => $this->className['className'],
                'date' => date('Y-m-d'),
                'time' => date('H:i:s'),
            ]
        );

        return $classTemplate->render();
    }
}
