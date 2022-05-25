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
class RepositoryInterfaceGenerator extends AbstractGenerator
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
        $this->sourceFile = $this->layerPatternPath.DIRECTORY_SEPARATOR.$this->getShortInterfaceName($this->name, $this->type).".php";
        
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS])) {
            throw new Exception(sprintf("Methods for repository '%s' was not found!", $this->name));
        }
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
                if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                    $className = $this->getValueObjectClassName($arg);
                    $this->addUseStatement($className);
                    $shortClassName = $this->getValueObjectShortClassName($arg);
                    $propertyName = lcfirst($shortClassName);
                    $methodArguments[] = $shortClassName." $".$propertyName;
                } elseif (
                    $type === DataTypeInterface::STRUCTURE_TYPE_ENTITY ||
                    $type === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
                ) {
                    $this->addUseStatement($this->getInterfaceName($arg, $type));
                    $shortClassName = $this->getShortInterfaceName($arg, $type);
                    $propertyName = lcfirst($shortClassName);
                    $methodArguments[] = $shortClassName." $".$propertyName;
                } elseif (strpos($type, "\\")) {
                    $this->addUseStatement($type);
                    $classNameArray = explode("\\", $type);
                    $type = array_pop($classNameArray);
                    $propertyName = lcfirst($type);
                    $methodArguments[] = $type." $".$propertyName;
                }  else {
                    $methodArguments[] = $type." $".$this->underscoreAndHyphenToCamelCase($arg);
                }
            }
            $returns = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

            if (!is_array($returns)) {
                $returns = [$returns];
            }
            $returnTypes = [];

            foreach ($returns as $name => $returnType) {
                if ($returnType === DataTypeInterface::STRUCTURE_TYPE_ENTITY) {
                    $this->addUseStatement($this->getInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
                    $shortClassName = $this->getShortInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
                    $returnTypes[] = $shortClassName;
                } elseif (strpos($returnType, "\\")) {
                    $this->addUseStatement($returnType);
                    $classNameArray = explode("\\", $returnType);
                    $returnTypes[] = array_pop($classNameArray);
                } else {
                    $returnTypes[] = $returnType;
                }
            }
            $methodComment = sprintf("%s %s in Storage.", ucfirst($methodName), $shortClassName);
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_INTERFACE,
                $methodComment,
                $this->underscoreAndHyphenToCamelCase($methodName),
                implode(", ", $methodArguments),
                implode("|", $returnTypes),
                "",
                ""
            );
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }
}