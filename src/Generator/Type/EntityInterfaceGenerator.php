<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

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
class EntityInterfaceGenerator extends AbstractGenerator
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
        $implements = [];
        $useTraits = [];
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
    
        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name])) {
            throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
        }
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $this->addUseStatement(sprintf("%s;", $this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)));
        array_unshift($entityValueObject, self::KEY_UNIQUE_UUID);
        array_unshift($entityValueObject, self::KEY_UNIQUE_PROCESS_UUID);

        foreach ($entityValueObject as $valueObject) {
            $renderedMethod = $this->renderValueObjectMethod($valueObject);

            if (null === $renderedMethod) {
                continue;
            }
            $methods[] = $renderedMethod;
        }

        foreach ($this->structure as $command) {
            $methods[] = $this->renderCommandMethod($command);
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }

    protected function renderValueObjectMethod(string $valueObject): ?string
    {
        $className = $this->getValueObjectClassName($valueObject);
        $this->addUseStatement($className);
        $shortClassName = $this->getValueObjectShortClassName($valueObject);

        if (in_array($valueObject, self::UNIQUE_KEYS)) {
            return null;
        }
        $methodName = "get".$shortClassName;
        $methodComment = sprintf("Return %s value object.", $valueObject);
        $defaultValue = DataTypeInterface::DATA_TYPE_NULL;

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            "",
            "?".$shortClassName,
            "",
            ""
        );
    }

    protected function renderCommandMethod(string $command): string
    {
        $commandArgs = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$command][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $methodName = $command;
        $methodComment = sprintf("Execute %s command.", $command);
        $commandArguments = [];

        foreach ($commandArgs as $arg) {
            if ($arg === self::KEY_UNIQUE_UUID) {
                continue;
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            $commandArguments[] = $shortClassName." $".$propertyName;
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            implode(", ", $commandArguments),
            "",
            "",
            ""
        );
    }
}
