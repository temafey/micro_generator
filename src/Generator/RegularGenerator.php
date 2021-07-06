<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

use Exception;
use RuntimeException;

/**
 * Generator for base test class skeletons from classes.
 *
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 */
class RegularGenerator extends AbstractGenerator
{
    /**
     * Test modules.
     *
     * @var string[]
     */
    protected $modules = [];

    /**
     * Source path.
     *
     * @var string|null
     */
    protected $sourcePath;

    /**
     * Constructor.
     *
     * @param string   $className
     * @param string   $sourceFile
     * @param string[] $modules
     * @param string   $sourcePath
     *
     * @throws RuntimeException
     */
    public function __construct(string $className = '', string $sourceFile = '', array $modules = [], ?string $sourcePath = null)
    {
        $this->className = $this->parseFullyQualifiedClassName(
            $className
        );

        $this->sourceFile = str_replace(
            $this->className['fullyQualifiedClassName'],
            $this->className['className'],
            $sourceFile
        );

        $this->modules = $modules;
        $this->sourcePath = $sourcePath;
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
        $oathinfo = pathinfo($this->sourceFile);
        $classTemplate = new Template(
            sprintf(
                '%s%stemplate%s' . $oathinfo['filename'] . '.tpl',
                __DIR__,
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR
            )
        );
        $testSuites = $this->getTestsuites($this->modules);
        $modules = $this->getModules($this->modules);

        $classTemplate->setVar(
            [
                'testsuites' => $testSuites,
                'modules' => $modules,
                'moduleDir' => $this->sourcePath,
                'testClassName' => $this->className['className'],
                'date' => date('Y-m-d'),
                'time' => date('H:i:s'),
            ]
        );

        return $classTemplate->render();
    }

    /**
     * Genereate test suites.
     *
     * @param mixed[] $modules
     *
     * @return string
     */
    public function getTestsuites(array $modules): string
    {
        $testsuiteModules = [];

        foreach ($modules as $module => $tests) {
            $testsuite = '<testsuite name="' . $module . ' Test Suite">';
            $testsuitefiles = [];

            foreach ($tests as $localTestPath) {
                $testsuitefiles[] = '<file>' . $localTestPath . '</file>';
            }

            $testsuite .= "\r\n\t\t\t" . implode("\r\n\t\t\t", $testsuitefiles);
            $testsuite .= "\r\n\t\t" . '</testsuite>';

            $testsuiteModules[] = $testsuite;
        }

        return implode("\r\n\t\t", $testsuiteModules);
    }

    /**
     * Genereate modules array.
     *
     * @param string[] $modules
     *
     * @return string
     */
    public function getModules(array $modules): string
    {
        $testSuiteModules = [];

        foreach (array_keys($modules) as $module) {
            $testSuiteModules[] = '\'' . $module . '\'';
        }

        return implode(', ', $testSuiteModules);
    }
}
