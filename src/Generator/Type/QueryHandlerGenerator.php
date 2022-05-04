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
class QueryHandlerGenerator extends AbstractGenerator
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
        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$this->name])) {
            throw new Exception(sprintf("Command for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
            throw new Exception(sprintf("Repository for handler '%s' was not found!", $this->name));
        }
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
 
        if ($this->useCommonComponent) {
            $this->addUseStatement("MicroModule\Common\Application\QueryHandler\QueryHandlerInterface");
        } else {
            $this->addUseStatement($classNamespace. "\\"."QueryHandlerInterface");
        }
        $extends = "";
        $implements[] = "QueryHandlerInterface";
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_QUERY));

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $type => $arg) {
            $className = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getInterfaceName($arg, $type) : $this->getClassName($arg, $type);
            $this->addUseStatement($className);
            $shortClassName = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getShortInterfaceName($arg, $type) : $this->getShortClassName($arg, $type);
            $propertyName = lcfirst($this->getShortClassName($arg, $type));
            $propertyComment = "";
            $this->addProperty($propertyName, $shortClassName, $propertyComment);
            $this->constructArguments[] = $shortClassName." $".$propertyName;
            $this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            implode(", ", $this->constructArguments),
            "",
            implode("", $this->constructArgumentsAssignment),
            ""
        );
        $methods[] = $this->renderHandleMethod();

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderHandleMethod(): string
    {
        $queryStructure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$this->name];
        $queryShortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_QUERY);
        $queryPropertyName = lcfirst($queryShortClassName);
        $repositoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY], DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
        $valueObjects = [];
        $methodBody = "";

        foreach ($queryStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $valueObjects[] = sprintf("\$%s->get%s()", $queryPropertyName, $shortClassName);
        }
        $return = sprintf("\$this->%s->%s(%s)", $repositoryShortName, $this->underscoreAndHyphenToCamelCase($this->name), implode(", ", $valueObjects));

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            sprintf("Handle %s query.", $queryShortClassName),
            "handle",
            $queryShortClassName." $".$queryPropertyName,
            "?array",
            $methodBody,
            $return
        );
    }
}
