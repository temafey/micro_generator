<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type\Repository;

use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Helper\ReturnTypeNotFoundException;
use Exception;
use ReflectionException;

/**
 * Generator for
 *
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 *
 * @SuppressWarnings(PHPMD)
 */
class TaskInterfaceGenerator extends AbstractGenerator
{
    /**
     * Generate test class code.
     *
     * @return string|null
     *
     * @throws Exception
     * @throws ReflectionException
     * @throws ReturnTypeNotFoundException
     *
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function generate(): ?string
    {
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);

        foreach ($this->structure as $entityName => $commands) {
            foreach ($commands as $commandName => $command) {
                $methods[] = $this->renderStructureMethod($entityName, $commandName, $command);
            }
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }

    public function renderStructureMethod(string $entityName, string $commandName, array $structure): string
    {
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
            throw new Exception(sprintf("Arguments for task repository method '%s' was not found!", $commandName));
        }
        $methodArguments = [];

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if ($arg === self::UNIQUE_KEY_UUID) {
                $this->addUseStatement("Ramsey\Uuid\UuidInterface");
                $shortClassName = "UuidInterface";
            } elseif ($this->useCommonComponent && $arg === self::UNIQUE_KEY_PROCESS_UUID) {
                $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
                $shortClassName = "ProcessUuid";
            } elseif ($this->useCommonComponent && $arg === self::UNIQUE_KEY_FIND_CRITERIA) {
                $this->addUseStatement("MicroModule\Common\Domain\ValueObject\FindCriteria");
                $shortClassName = "FindCriteria";
            } else {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            }
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $shortClassName." $".$propertyName;
        }
        $commandName = ucfirst($this->underscoreAndHyphenToCamelCase($commandName));
        $methodComment = sprintf("Send `%s Command` into queue.", $commandName);
        $methodName = sprintf("add%sTask", $commandName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            "void",
            "",
            ""
        );
    }
}
