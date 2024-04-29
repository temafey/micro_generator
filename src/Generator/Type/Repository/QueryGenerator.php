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

        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL])) {
            throw new Exception(sprintf("Entity for repository '%s' was not found!", $this->name));
        }
        $extends = "";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type, $this->name);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($interfaceNamespace." as QueryRepositoryInterface");
        $this->addUseStatement("MicroModule\Base\Infrastructure\Repository\Exception\NotFoundException"); 
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $implements[] = "QueryRepositoryInterface";
        $readModel = $this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL]?:'ReadModel';
        $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
        $addVar = [
            "shortEntityName" => $this->underscoreAndHyphenToCamelCase($entityName),
            "entityName" => ucfirst($this->underscoreAndHyphenToCamelCase($entityName)),
            "criteriaParams" => [],
        ];
        $methods[] = $this->renderConstructMethod();

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $queryName => $methodName) {
            $methods[] = $this->renderStructureMethod($queryName, $methodName);
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
                $constructArgument = "protected ".$shortClassName." $".$propertyName;
            } elseif ($type === DataTypeInterface::STRUCTURE_TYPE_ENTITY) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
                $propertyName = lcfirst($shortClassName);
                $constructArgument = "protected ".$shortClassName." $".$propertyName;
            } elseif (strpos($type, "\\")) {
                $this->addUseStatement($type);
                $classNameArray = explode("\\", $type);
                $type = array_pop($classNameArray);
                $propertyName = lcfirst(str_replace(["Interface", "interface"], "", $type));
                $constructArgument = sprintf("protected %s $%s", $type, $propertyName);

                if (strpos($type, "ReadModelStoreInterface") !== false) {
                    $constructArgument = sprintf(
                            "#[Autowire(service: '%s.infrastructure.repository.storage.read_model.dbal')]\n\t\t",
                            $this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL]
                        ).$constructArgument;
                }
            }  else {
                $propertyName = lcfirst($arg);
                $constructArgument = "protected ".$type." $".$this->underscoreAndHyphenToCamelCase($arg);
            }
            $this->constructArguments[] = $constructArgument;
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
            "",
            implode("", $this->constructArgumentsAssignment),
            ""
        );
    }

    public function renderStructureMethod(string $queryName, string $methodName): string
    {
        $structure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$queryName];
        $addVar = [];
        $addVar["criteriaParams"] = [];

        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
            throw new Exception(sprintf("Arguments for repository method '%s' was not found!", $queryName));
        }
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
            //throw new Exception(sprintf("Return type for repository method '%s' was not found!", $commandName));
        }
        $methodArguments = [];
        $methodComment = "";

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if (is_numeric($arg)) {
                $arg = $type;
                $type = DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT;
            }
            
            if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                $shortClassName = $this->getValueObjectShortClassName($arg);

                if ($shortClassName === self::VALUE_OBJECT_UNIQUE_PROCESS_UUID) {
                    continue;
                }
                $this->addUseStatement($this->getValueObjectClassName($arg));
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
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
            $readModelName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL];
            $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN] = $this->getShortClassName($readModelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE);
            $this->addUseStatement($this->getClassName($readModelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE));
        }
        $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

        if (is_array($returnType)) {
            $name = key($returnType);
            $returnType = $returnType[$name];
        }
        $methodName = $this->getQueryRepositoryMethodName($methodName, $readModelName);
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
        $addVar["readModelName"] = ucfirst($this->underscoreAndHyphenToCamelCase($this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL]));

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
            case self::METHOD_TYPE_FIND_ALL:
            case self::METHOD_TYPE_FIND:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_BY_CRITERIA;
                break;

            case self::METHOD_TYPE_FETCH_ONE:
            case self::METHOD_TYPE_FETCH:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_BY_UUID;
                break;

            case self::METHOD_TYPE_FIND_ONE_BY:
            case self::METHOD_TYPE_FIND_ONE:
            default:
                $methodTemplate = self::METHOD_TEMPLATE_TYPE_FIND_ONE_BY;
                break;
        }

        return $methodTemplate;
    }
}
