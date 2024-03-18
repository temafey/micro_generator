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
            $this->addUseStatement("MicroModule\Common\Domain\Query\QueryInterface");
            $this->addUseStatement("MicroModule\Common\Application\QueryHandler\QueryHandlerInterface");
        } else {
            $this->addUseStatement($classNamespace. "\\"."QueryHandlerInterface");
        }
        $extends = "";
        $implements[] = "QueryHandlerInterface";
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_QUERY));

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $type => $arg) {
            if (is_numeric($type)) {
                //$type = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type'];
                $type = DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT;
                $className = $this->getValueObjectClassName($arg);
                $shortClassName = $this->getValueObjectShortClassName($arg);
                $propertyName = lcfirst($shortClassName);
                $propertyComment = sprintf("%s value object.", $shortClassName);
            } else {
                $className = $this->getInterfaceName($arg, $type);
                $shortClassName = $this->getShortInterfaceName($arg, $type);
                $propertyName = lcfirst($this->getShortClassName($arg, $type));
                $propertyComment = sprintf("%s %s.", ucfirst($propertyName), $type);
            }
            $this->addUseStatement($className);
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
            
            if ($shortClassName === self::VALUE_OBJECT_UNIQUE_PROCESS_UUID) {
                continue;
            }
            $valueObjects[] = sprintf("\$%s->get%s()", $queryPropertyName, $shortClassName);
        }

        if ($this->useCommonComponent) {
            $methodComment = sprintf("Handle %s query.\r\n\t *\r\n\t * @param QueryInterface|%s $%s", $queryShortClassName, $queryShortClassName, $queryPropertyName);
            $queryShortClassName = "QueryInterface";
        } else {
            $methodComment = sprintf("Handle %s query.", $queryShortClassName);
        }
        $return = sprintf("\$this->%s->%s(%s)", $repositoryShortName, $this->underscoreAndHyphenToCamelCase($this->name), implode(", ", $valueObjects));

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            "handle",
            $queryShortClassName." $".$queryPropertyName,
            "?array",
            $methodBody,
            $return
        );
    }
}
