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
            return null;
            throw new Exception(sprintf("Methods for repository '%s' was not found!", $this->name));
        }
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type, $this->name);

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $commandMethodName => $structure) {
            $methods[] = $this->renderInterfaceMethod($commandMethodName, $structure);
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }
    
    protected function renderInterfaceMethod(string|int $commandMethodName, array|string $structure): string
    {
        if (!is_array($structure)) {
            if (is_numeric($commandMethodName)) {
                $commandMethodName = $structure;
            }
            $methodName = $structure;

            if ($this->name === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL) {
                $structure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandMethodName];
            } else {
                $structure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$commandMethodName];
                
                if (isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$commandMethodName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
                    $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN] = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$commandMethodName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];
                }
            }
        }
        if ($this->name === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL) {
            $methodName = $this->getReadModelRepositoryMethodName($commandMethodName);
        } elseif ($this->name === DataTypeInterface::STRUCTURE_TYPE_QUERY) {
            $methodName = $this->getQueryRepositoryMethodName($methodName, $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY]);
        }
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
            throw new Exception(sprintf("Arguments for repository method '%s' was not found!", $commandMethodName));
        }
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
            $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE);
            $this->addUseStatement($this->getClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE));
        }
        $methodArguments = [];
        $methodComment = "";

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            $methodArgument = $this->processMethodArgument($arg, $type);

            if (!$methodArgument) {
                continue;
            }
            $methodArguments[] = $methodArgument;
        }
        $returns = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

        if (!is_array($returns)) {
            $returns = [$returns];
        }
        $returnTypes = [];
        $methodComment = "";

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
        //$methodComment = sprintf("%s %s in Storage.", ucfirst($methodName), $shortClassName);
        
        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $this->underscoreAndHyphenToCamelCase($methodName),
            implode(", ", $methodArguments),
            implode("|", $returnTypes),
            "",
            "",
            [],
            true
        );
    }
    
    protected function processMethodArgument(string|int $arg, string $type): ?string
    {
        if (is_numeric($arg)) {
            $arg = $type;
            $type = DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT;
        }
        if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
            $shortClassName = $this->getValueObjectShortClassName($arg);

            if (
                $this->type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE &&
                $this->name === DataTypeInterface::STRUCTURE_TYPE_QUERY &&
                $shortClassName === self::VALUE_OBJECT_UNIQUE_PROCESS_UUID
            ) {
                return null;
            }
            $className = $this->getValueObjectClassName($arg);
            $this->addUseStatement($className);
            $propertyName = lcfirst($shortClassName);
            $methodArgument = $shortClassName." $".$propertyName;
        } elseif (
            $type === DataTypeInterface::STRUCTURE_TYPE_ENTITY ||
            $type === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
        ) {
            $this->addUseStatement($this->getInterfaceName($arg, $type));
            $shortClassName = $this->getShortInterfaceName($arg, $type);
            $propertyName = lcfirst($shortClassName);
            $methodArgument = $shortClassName." $".$propertyName;
        } elseif (strpos($type, "\\")) {
            $this->addUseStatement($type);
            $classNameArray = explode("\\", $type);
            $type = array_pop($classNameArray);
            $propertyName = lcfirst($type);
            $methodArgument = $type." $".$propertyName;
        }  else {
            $methodArgument = $type." $".$this->underscoreAndHyphenToCamelCase($arg);
        }

        return $methodArgument;
    }
}
