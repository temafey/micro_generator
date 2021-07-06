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
class RpcMethodGenerator extends AbstractGenerator
{
    /**
     * Is create type.
     */
    protected $typeCreate = false;

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
        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$this->name])) {
            throw new Exception(sprintf("Command for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
            throw new Exception(sprintf("Repository for handler '%s' was not found!", $this->name));
        }
        $useStatement = [];
        $implements = [];
        $useTraits = [];
        $properties = [];
        $constructArguments = [];
        $constructArgumentsInitialize = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $useStatement[] = "\r\nuse ".$classNamespace. "\\"."CommandHandlerInterface;";
        $extends = "";
        $implements[] = "CommandHandlerInterface";
        $useStatement[] = sprintf("\r\nuse %s;", $this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND));

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $type => $arg) {
            $className = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getInterfaceName($arg, $type) : $this->getClassName($arg, $type);
            $useStatement[] = sprintf("\r\nuse %s;", $className);
            $shortClassName = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getShortInterfaceName($arg, $type) : $this->getShortClassName($arg, $type);
            $propertyName = lcfirst($this->getShortClassName($arg, $type));
            $propertyComment = "";
            $properties[] = $this->renderProperty(
                self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
                $propertyComment,
                DataTypeInterface::PROPERTY_VISIBILITY_PROTECTED,
                $shortClassName,
                $propertyName
            );
            $constructArguments[] = $shortClassName." $".$propertyName;
            $constructArgumentsInitialize[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);

            if ($type === DataTypeInterface::STRUCTURE_TYPE_FACTORY) {
                $this->typeCreate = true;
            }
        }
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            implode(", ", $constructArguments),
            "",
            implode("", $constructArgumentsInitialize),
            ""
        );
        $methods[] = $this->renderHandleMethod();

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $useStatement,
            $extends,
            $implements,
            $useTraits,
            $properties,
            $methods
        );
    }

    protected function renderHandleMethod(): string
    {
        $commandStructure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$this->name];
        $commandShortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND);
        $commandPropertyName = lcfirst($commandShortClassName);
        $entityShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $repositoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY], DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
        $valueObjects = [];
        $methodBody = "";

        foreach ($commandStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if (!$this->typeCreate && $arg === DataTypeInterface::DATA_TYPE_UUID) {
                continue;
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $valueObjects[] = sprintf("\$%s->get%s()", $commandPropertyName, $shortClassName);
        }

        if ($this->typeCreate) {
            $factoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_FACTORY], DataTypeInterface::STRUCTURE_TYPE_FACTORY));
            $methodBody .= sprintf("\r\n\t\t\$%s = \$this->%s->createInstance(%s);", $entityShortName, $factoryShortName, implode(", ", $valueObjects));
        } else {
            $methodBody .= sprintf("\r\n\t\t\$%s = \$this->%s->get(\$%s->getUuid());", $entityShortName, $repositoryShortName, $commandPropertyName);
            $methodBody .= sprintf("\r\n\t\t\$%s->%s(%s);", $entityShortName, $this->name, implode(", ", $valueObjects));
        }
        $methodBody .= sprintf("\r\n\t\t\$this->%s->store(\$%s);", $repositoryShortName, $entityShortName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_VOID,
            sprintf("Handle %s command.", $commandShortClassName),
            "handle",
            $commandShortClassName." $".$commandPropertyName,
            "void",
            $methodBody,
            ""
        );
    }
}
