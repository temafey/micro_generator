<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Helper;

use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\CodeExtractException;
use MicroModule\MicroserviceGenerator\Generator\Exception\FileNotExistsException;
use MicroModule\MicroserviceGenerator\Generator\Exception\GeneratorException;
use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;
use Nette\Utils\Strings;
use PhpParser\{Node\Stmt\ClassMethod, ParserFactory, Node, NodeFinder};
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;


/**
 * Trait CodeHelper.
 *
 * @SuppressWarnings(PHPMD)
 */
trait CodeHelper
{
    protected string $projectNamespace;

    /**
     * Generate class name with namespace by class pattern type.
     *
     * @throws InvalidClassTypeException
     */
    protected function getClassName(string $name, string $type): string
    {
        $classNamespace = $this->getClassNamespace($type);

        return $classNamespace.'\\'.$this->getShortClassName($name, $type);
    }

    /**
     * Generate class name with namespace by class pattern type.
     *
     * @throws InvalidClassTypeException
     */
    protected function getInterfaceName(string $name, string $type): string
    {
        $classNamespace = $this->getInterfaceNamespace($type);

        return $classNamespace.'\\'.ucfirst($this->underscoreAndHyphenToCamelCase($name)).$this->getClassNameSuffix($type)."Interface";
    }

    /**
     * Generate command factory constant.
     */
    protected function getCommandFactoryConst(string $name): string
    {
        //$entity = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$name][DataTypeInterface::STRUCTURE_TYPE_ENTITY];
        //$entity = strtoupper($this->underscoreAndHyphenToCamelCase($entity, "_"));
        $name = strtoupper($this->underscoreAndHyphenToCamelCase($name, "_"));

        return sprintf("%s_COMMAND", $name);
    }

    /**
     * Generate class name without namespace by pattern type.
     */
    protected function getShortClassName(string $name, string $type): string
    {
        if ($type === DataTypeInterface::STRUCTURE_TYPE_EVENT) {
            if (isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
                $entity = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            } else {
                $entity = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$name][DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            }
            $name = ucfirst($this->underscoreAndHyphenToCamelCase($name));
            $name .= ($name[-1] === 'e') ? 'd' : 'ed';
            //$entity = ucfirst($this->underscoreAndHyphenToCamelCase($entity));
            //$name = sprintf("%s%s", $entity, $name);
        } else {
            $name = ucfirst($this->underscoreAndHyphenToCamelCase($name));
        }

        if ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK) {
            $type = "taskRepository";
        }

        return $name.$this->getClassNameSuffix($type);
    }

    /**
     * Generate interface name without namespace by pattern type.
     */
    protected function getShortInterfaceName(string $name, string $type): string
    {
        $type = str_replace(["Interface", "interface"], "", $type);

        if ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK) {
            $type = "taskRepository";
        }

        return ucfirst($this->underscoreAndHyphenToCamelCase($name)).$this->getClassNameSuffix($type)."Interface";
    }

    /**
     * Return class name with prefix if needed.
     */
    protected function getClassNameSuffix(string $type): string
    {
        switch ($type) {
            case DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT:
            case DataTypeInterface::STRUCTURE_TYPE_CLI:
                $suffix = '';
                break;

            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER:
            case DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER:
                $suffix = 'Handler';
                break;

            case DataTypeInterface::STRUCTURE_TYPE_RPC:
                $suffix = 'Method';
                break;

            default:
                $suffix = ucfirst($type);
                break;
        }

        return $suffix;
    }

    /**
     * Generate class namespace by pattern type.
     */
    protected function getClassNamespace(string $type): string
    {
        $layerNamespace = $this->getLayerNamespace($type);
        $type = str_replace(["Interface", "taskCommandHandler", "taskCommand"], ["", "commandHandler\Task", "command\Task"], $type);
        
        return $layerNamespace.'\\'.ucfirst($this->underscoreAndHyphenToCamelCase($type));
    }

    /**
     * Generate interface namespace by pattern type.
     */
    protected function getInterfaceNamespace(string $type): string
    {
        $layerNamespace = $this->getLayerInterfaceNamespace($type);

        if ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK) {
            $layerFolder = ucfirst(DataTypeInterface::STRUCTURE_TYPE_REPOSITORY);
        } else {
            $layerFolder = ucfirst($this->underscoreAndHyphenToCamelCase(str_replace(["Interface", "interface"], "", $type)));
        }

        return $layerNamespace.'\\'.$layerFolder;
    }

    /**
     * Return project layer namespace by pattern type.
     */
    protected function getLayerNamespace(string $type): string
    {
        $layer = $this->getLayerName($type);
        
        return $this->projectNamespace.'\\'.ucfirst($this->underscoreAndHyphenToCamelCase($layer));
    }

    /**
     * Return project layer interface namespace by pattern type.
     */
    protected function getLayerInterfaceNamespace(string $type): string
    {
        $layer = $this->getLayerInterface($type);

        return $this->projectNamespace.'\\'.ucfirst($this->underscoreAndHyphenToCamelCase($layer));
    }

    /**
     * Generate value object class name by pattern type.
     */
    protected function getValueObjectClassName(string $type): string
    {
        return DataTypeInterface::VALUE_OBJECT_NAMESPACE.ucfirst($this->underscoreAndHyphenToCamelCase($type, "\\"));
    }

    /**
     * Generate value object class short name by pattern type.
     */
    protected function getValueObjectShortClassName(string $type): string
    {
        $classNameArray = explode("\\", $this->getValueObjectClassName($type));

        return array_pop($classNameArray);
    }

    /**
     * Generate value object class short name by pattern type.
     */
    protected function getValueObjectScalarType(string $type): string
    {
        if (!isset(DataTypeInterface::DOMAIN_VALUE_OBJECT_TO_SCALAR_MAP[$type])) {
            throw new GeneratorException(sprintf("ValueObject type '%s' was not found in DOMAIN_VALUE_OBJECT_TO_SCALAR_MAP", $type));
        }

        return DataTypeInterface::DOMAIN_VALUE_OBJECT_TO_SCALAR_MAP[$type];
    }

    /**
     * Return layer name by pattern type.
     *
     * @throws InvalidClassTypeException
     */
    protected function getLayerName(string $type): string
    {
        switch ($type) {
            case DataTypeInterface::STRUCTURE_TYPE_ENTITY:
            case DataTypeInterface::STRUCTURE_TYPE_ENTITY_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_READ_MODEL:
            case DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_QUERY:
            case DataTypeInterface::STRUCTURE_TYPE_EVENT:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT:
            case DataTypeInterface::STRUCTURE_TYPE_SERVICE:
            case DataTypeInterface::STRUCTURE_TYPE_FACTORY:
            case DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE:
                $layer = DataTypeInterface::STRUCTURE_LAYER_DOMAIN;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER:
            case DataTypeInterface::STRUCTURE_TYPE_SAGA:
            case DataTypeInterface::STRUCTURE_TYPE_PROJECTOR:
            case DataTypeInterface::STRUCTURE_TYPE_DTO:
                $layer = DataTypeInterface::STRUCTURE_LAYER_APPLICATION;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS:
                $layer = DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_RPC:
            case DataTypeInterface::STRUCTURE_TYPE_CLI:
                $layer = DataTypeInterface::STRUCTURE_LAYER_PRESENTATION;
                break;

            default:
                throw new InvalidClassTypeException(sprintf('Structure type \'%s\' does not exist.', $type));
        }

        return $layer;
    }

    /**
     * Return layer interface name by pattern type.
     *
     * @throws InvalidClassTypeException
     */
    protected function  getLayerInterface(string $type): string
    {
        switch ($type) {
            case DataTypeInterface::STRUCTURE_TYPE_ENTITY:
            case DataTypeInterface::STRUCTURE_TYPE_ENTITY_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_READ_MODEL:
            case DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_QUERY:
            case DataTypeInterface::STRUCTURE_TYPE_EVENT:
            case DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT:
            case DataTypeInterface::STRUCTURE_TYPE_SERVICE:
            case DataTypeInterface::STRUCTURE_TYPE_FACTORY:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE:
            case DataTypeInterface::STRUCTURE_TYPE_FACTORY:
            case DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE:
                $layer = DataTypeInterface::STRUCTURE_LAYER_DOMAIN;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER:
            case DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK:
            case DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER:
            case DataTypeInterface::STRUCTURE_TYPE_SAGA:
            case DataTypeInterface::STRUCTURE_TYPE_PROJECTOR:
                $layer = DataTypeInterface::STRUCTURE_LAYER_APPLICATION;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS:
                $layer = DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE;
                break;

            case DataTypeInterface::STRUCTURE_TYPE_RPC:
            case DataTypeInterface::STRUCTURE_TYPE_CLI:
                $layer = DataTypeInterface::STRUCTURE_LAYER_PRESENTATION;
                break;

            default:
                throw new InvalidClassTypeException(sprintf('Structure type \'%s\' does not exist.', $type));
        }

        return $layer;
    }

    /**
     * @see https://regex101.com/r/rl1nvl/1
     */
    protected string $BIG_LETTER_REGEX = '#([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]*)#';

    public function underscoreAndHyphenToCamelCase(string $value, string $glue = ''): string
    {
        $underscoreToHyphensValue = str_replace(['_', '-'], ' ', $value);
        $uppercasedWords = ucwords($underscoreToHyphensValue);
        $value = str_replace(' ', $glue, $uppercasedWords);

        return lcfirst($value);
    }

    public function camelCaseToUnderscore(string $input): string
    {
        return $this->camelCaseToGlue($input, '_');
    }

    public function camelCaseToDashed(string $input): string
    {
        return $this->camelCaseToGlue($input, '-');
    }

    /**
     * @param mixed[] $items
     * 
     * @return mixed[]
     */
    public function camelCaseToUnderscoreInArrayKeys(array $items): array
    {
        foreach ($items as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $newKey = $this->camelCaseToUnderscore($key);
            
            if ($key === $newKey) {
                continue;
            }
            $items[$newKey] = $value;
            unset($items[$key]);
        }

        return $items;
    }

    protected function camelCaseToGlue(string $input, string $glue): string
    {
        $matches = Strings::matchAll($input, $this->BIG_LETTER_REGEX);
        $parts = [];
        
        foreach ($matches as $match) {
            $parts[] = $match[0] === strtoupper($match[0]) ? strtolower($match[0]) : lcfirst($match[0]);
        }

        return implode($glue, $parts);
    }
}
