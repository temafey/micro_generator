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
class QueryGenerator extends AbstractGenerator
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
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS])) {
            throw new Exception(sprintf("Methods for repository '%s' was not found!", $this->name));
        }

        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for repository '%s' was not found!", $this->name));
        }
        $extends = "";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($interfaceNamespace);
        $this->addUseStatement("MicroModule\Common\Infrastructure\Repository\Exception\NotFoundException");
        $implements[] = $interfaceShortName;
        $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY]?:'Entity';
        $addVar = [
            "shortEntityName" => $this->underscoreAndHyphenToCamelCase($entityName),
            "entityName" => ucfirst($this->underscoreAndHyphenToCamelCase($entityName)),
            "criteriaParams" => [],
        ];
        $methods[] = $this->renderConstructMethod();

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $methodName => $structure) {
            $methods[] = $this->renderStructureMethod($methodName, $structure, $addVar);
        }
        $addVar["criteriaParams"] = implode("", $addVar["criteriaParams"]);

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods,
            $addVar
        );
    }

    public function renderConstructMethod(): string
    {
        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                $this->addUseStatement($this->getValueObjectClassName($arg));
                $shortClassName = $this->getValueObjectShortClassName($arg);
                $propertyName = lcfirst($shortClassName);
                $this->constructArguments[] = $shortClassName." $".$propertyName;
            } elseif ($type === DataTypeInterface::STRUCTURE_TYPE_ENTITY) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
                $propertyName = lcfirst($shortClassName);
                $methodArguments[] = $shortClassName." $".$propertyName;
            } elseif (strpos($type, "\\")) {
                $this->addUseStatement($type);
                $classNameArray = explode("\\", $type);
                $type = array_pop($classNameArray);
                $propertyName = lcfirst(str_replace(["Interface", "interface"], "", $type));
                $this->constructArguments[] = $type." $".$propertyName;
            }  else {
                $propertyName = lcfirst($arg);
                $this->constructArguments[] = $type." $".$this->underscoreAndHyphenToCamelCase($arg);
            }
            $propertyComment = "";
            $this->addProperty($propertyName, $type, $propertyComment);
            $this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            implode(", ", $this->constructArguments),
            "",
            implode("", $this->constructArgumentsAssignment),
            ""
        );
    }

    public function renderStructureMethod(string $methodName, array $structure, array $addVar = []): string
    {
        $addVar["criteriaParams"] = [];

        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
            throw new Exception(sprintf("Arguments for repository method '%s' was not found!", $methodName));
        }
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
            throw new Exception(sprintf("Return type for repository method '%s' was not found!", $methodName));
        }
        $methodArguments = [];
        $methodComment = "";

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                $this->addUseStatement($this->getValueObjectClassName($arg));
                $shortClassName = $this->getValueObjectShortClassName($arg);
                $propertyName = lcfirst($shortClassName);
                $methodArguments[] = $shortClassName." $".$propertyName;
                $addVar["criteriaParams"][] = "\"".$arg."\" => $".$propertyName."->toNative(), ";
            } elseif ($type === DataTypeInterface::STRUCTURE_TYPE_ENTITY) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
                $propertyName = lcfirst($shortClassName);
                $methodArguments[] = $shortClassName." $".$propertyName;
            } elseif (strpos($type, "\\")) {
                $this->addUseStatement($type);
                $classNameArray = explode("\\", $type);
                $type = array_pop($classNameArray);
                $propertyName = lcfirst(str_replace(["Interface", "interface"], "", $type));
                $methodArguments[] = $type." $".$propertyName;
            }  else {
                $methodArguments[] = $type." $".$this->underscoreAndHyphenToCamelCase($arg);;
            }
        }
        $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

        if (is_array($returnType)) {
            $name = key($returnType);
            $returnType = $returnType[$name];
        }
        $methodTemplate = $this->getMethodTemplateName($methodName);
        $methodLogic = "";

        if ($returnType === DataTypeInterface::DATA_TYPE_VOID) {
            $methodTemplate = self::METHOD_TEMPLATE_TYPE_VOID;
            $return = '';
        } elseif ($returnType === DataTypeInterface::STRUCTURE_TYPE_ENTITY) {
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $shortClassName = $this->getShortClassName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
            $returnType = $shortClassName;
            $return = "$".lcfirst($shortClassName);
        } elseif (strpos($returnType, "\\")) {
            $this->addUseStatement($returnType);
            $classNameArray = explode("\\", $returnType);
            $returnType = array_pop($classNameArray);
            $return = "$".lcfirst($returnType);
        } else {
            $return = "\$result";
        }
        $addVar["criteriaParams"] = implode("", $addVar["criteriaParams"]);

        return $this->renderMethod(
            $methodTemplate,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $returnType,
            $methodLogic,
            $return,
            $addVar
        );
    }

    /**
     * Compare method name and return special method template.
     */
    protected function getMethodTemplateName(string $methodName): string
    {
        switch ($methodName) {
            case self::METHOD_TYPE_FIND_BY_UUID:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_BY_UUID;
                break;

            case self::METHOD_TYPE_FIND_BY_CRITERIA:
            case self::METHOD_TYPE_FIND_BY_CRITERIA:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_BY_CRITERIA;
                break;

            case self::METHOD_TYPE_FIND_ONE_BY:
            default:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_ONE_BY;
                break;

        }

        return $methodTemplate;
    }
}
