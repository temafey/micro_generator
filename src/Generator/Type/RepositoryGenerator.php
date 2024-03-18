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
class RepositoryGenerator extends AbstractGenerator
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
        if ($this->layer === DataTypeInterface::STRUCTURE_LAYER_DOMAIN) {
            $this->sourceFile = $this->layerPatternPath.DIRECTORY_SEPARATOR.$this->getShortInterfaceName($this->name, $this->type).".php";
            return $this->generateInterface();
        }

        return $this->generateClass();
    }

    protected function generateClass(): ?string
    {
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS])) {
            throw new Exception(sprintf("Methods for repository '%s' was not found!", $this->name));
        }
        $useStatement = [];
        $extends = "";
        $implements = [];
        $useTraits = [];
        $properties = [];
        $constructArguments = [];
        $constructArgumentsInitialize = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $useStatement[] = sprintf("\r\nuse %s;", $interfaceNamespace);
        $implements[] = $interfaceShortName;

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            $fullClassName = "";

            if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                $fullClassName = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
                $propertyName = lcfirst($shortClassName);
                $constructArguments[] = $shortClassName." $".$propertyName;
                $propertyComment = sprintf("%s %s.", $shortClassName, $type);
            } elseif (strpos($type, "\\")) {
                $fullClassName = sprintf("\r\nuse %s;", $type);
                $classNameArray = explode("\\", $type);
                $type = array_pop($classNameArray);
                $propertyName = lcfirst($type);
                $constructArguments[] = $type." $".$propertyName;
                $propertyComment = sprintf("%s %s.", $propertyName, $type);
            }  else {
                $propertyName = $arg;
                $constructArguments[] = $type." $".$arg;
                $propertyComment = sprintf("%s %s.", $arg, $type);
            }

            if ($fullClassName !== "" && !in_array($fullClassName, $useStatement)) {
                $useStatement[] = $fullClassName;
            }
            $properties[] = $this->renderProperty(
                self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
                $propertyComment,
                DataTypeInterface::PROPERTY_VISIBILITY_PROTECTED,
                $type,
                $propertyName
            );
            $constructArgumentsInitialize[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
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

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $methodName => $structure) {
            if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
                throw new Exception(sprintf("Arguments for repository method '%s' was not found!", $methodName));
            }
            if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
                throw new Exception(sprintf("Return type for repository method '%s' was not found!", $methodName));
            }
            $methodArguments = [];
            $methodComment = "";

            foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
                $fullClassName = "";

                if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                    $fullClassName = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
                    $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
                    $propertyName = lcfirst($shortClassName);
                    $methodArguments[] = $shortClassName." $".$propertyName;
                } elseif (strpos($type, "\\")) {
                    $fullClassName = sprintf("\r\nuse %s;", $type);
                    $classNameArray = explode("\\", $type);
                    $type = array_pop($classNameArray);
                    $propertyName = lcfirst($type);
                    $methodArguments[] = $type." $".$propertyName;
                }  else {
                    $methodArguments[] = $type." $".$arg;
                }

                if ($fullClassName !== "" && !in_array($fullClassName, $useStatement)) {
                    $useStatement[] = $fullClassName;
                }
            }
            $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

            if (strpos($returnType, "\\")) {
                $fullClassName= sprintf("\r\nuse %s;", $returnType);

                if (!in_array($fullClassName, $useStatement)) {
                    $useStatement[] = $fullClassName;
                }
                $classNameArray = explode("\\", $returnType);
                $returnType = array_pop($classNameArray);
                $return = "$".lcfirst($returnType);
            } else {
                $return = "\$result";
            }
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                $methodName,
                implode(", ", $methodArguments),
                $returnType,
                "",
                $return
            );
        }

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

    public function generateInterface(): ?string
    {
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS])) {
            throw new Exception(sprintf("Methods for repository '%s' was not found!", $this->name));
        }
        $useStatement = [];
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $methodName => $structure) {
            if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
                throw new Exception(sprintf("Arguments for repository method '%s' was not found!", $methodName));
            }
            if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
                throw new Exception(sprintf("Return type for repository method '%s' was not found!", $methodName));
            }
            $methodArguments = [];
            $methodComment = "";

            foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
                $fullClassName = "";

                if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                    $fullClassName = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
                    $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
                    $propertyName = lcfirst($shortClassName);
                    $methodArguments[] = $shortClassName." $".$propertyName;
                } elseif (strpos($type, "\\")) {
                    $fullClassName = sprintf("\r\nuse %s;", $type);
                    $classNameArray = explode("\\", $type);
                    $type = array_pop($classNameArray);
                    $propertyName = lcfirst($type);
                    $methodArguments[] = $type." $".$propertyName;
                }  else {
                    $methodArguments[] = $type." $".$arg;
                }

                if ($fullClassName !== "" && !in_array($fullClassName, $useStatement)) {
                    $useStatement[] = $fullClassName;
                }
            }
            $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

            if (strpos($returnType, "\\")) {
                $fullClassName= sprintf("\r\nuse %s;", $returnType);

                if (!in_array($fullClassName, $useStatement)) {
                    $useStatement[] = $fullClassName;
                }
                $classNameArray = explode("\\", $returnType);
                $returnType = array_pop($classNameArray);
            }
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_INTERFACE,
                $methodComment,
                $methodName,
                implode(", ", $methodArguments),
                $returnType,
                "",
                ""
            );
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $useStatement,
            $methods
        );
    }
}
