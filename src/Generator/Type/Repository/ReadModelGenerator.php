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
class ReadModelGenerator extends AbstractGenerator
{
    public const READ_MODEL_STORE_METHOD_KEYS = [
        "insertOne" => [
            "add", "insert", "create", "save",
        ],
        "updateOne" => [
            "update",
        ],
        "deleteOne" => [
            "delete", "remove",
        ],
        "findOne" => [
          "findOne", "fetch",
        ],
    ];
    
    protected array $methods = [];

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
        $extends = "";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type, $this->name);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($interfaceNamespace);
        $this->addUseStatement("MicroModule\Base\Infrastructure\Repository\Exception\NotFoundException");
        $this->addUseStatement("MicroModule\Base\Domain\Exception\ReadModelException");
        $this->addUseStatement("MicroModule\Base\Infrastructure\Repository\Exception\DBALEventStoreException");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $implements[] = $interfaceShortName;
        $addVar = [
            "ReadModelException" => "ReadModelException",
            "criteriaParams" => [],
        ];
        $methods[] = $this->renderConstructMethod();

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS] as $methodName => $structure) {
            if (is_numeric($methodName)) {
                $methodName = $structure;
                $structure = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY]  => DataTypeInterface::STRUCTURE_TYPE_READ_MODEL,
                    ],
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_VOID,
                ];
                $type = DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT;
            }
            $methodName = $this->getReadModelRepositoryMethodName($methodName);

            if (in_array($methodName, $this->methods)) {
                continue;
            }
            if ($methodName === self::READ_MODEL_REPOSITORY_METHOD_NAME_GET) {
                $methods[] = $this->renderGetMethod();
                continue;
            }
            $this->methods[] = $methodName;
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
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
                $propertyType = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
                $propertyName = lcfirst($propertyType);
            }  elseif (
                $type === DataTypeInterface::STRUCTURE_TYPE_ENTITY ||
                $type === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
            ) {
                $this->addUseStatement($this->getClassName($arg, $type));
                $propertyType = $this->getShortClassName($arg, $type);
                $propertyName = lcfirst($propertyType);
            } elseif (strpos($type, "\\")) {
                $this->addUseStatement($type);
                $classNameArray = explode("\\", $type);
                $propertyType = array_pop($classNameArray);
                $propertyName = lcfirst(str_replace(["Interface", "interface"], "", $propertyType));
            }  else {
                $propertyName = $this->underscoreAndHyphenToCamelCase($arg);
                $propertyType = $type;
            }
            $constructArgument = sprintf("protected %s $%s", $propertyType, $propertyName);

            if (strpos($type, "ReadModelStoreInterface") !== false) {
                $constructArgument = sprintf(
                    "#[Autowire(service: '%s.infrastructure.repository.storage.read_model.dbal')]\n\t\t",
                    $this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL]
                ).$constructArgument;
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
        $readModelArguments = [];
        $methodComment = "";

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT) {
                $className = $this->getValueObjectClassName($arg);
                $this->addUseStatement($className);
                $shortClassName = $this->getValueObjectShortClassName($arg);
                $propertyName = lcfirst($shortClassName);
                $methodArguments[] = $shortClassName." $".$propertyName;
                $readModelArguments[] = "$".$propertyName;
                $addVar["criteriaParams"][] = "\"".$arg."\" => $".$propertyName."->toNative(), ";
            } elseif (
                $type === DataTypeInterface::STRUCTURE_TYPE_ENTITY ||
                $type === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
            ) {
                $this->addUseStatement($this->getInterfaceName($arg, $type));
                $shortInterfaceName = $this->getShortInterfaceName($arg, $type);
                $shortClassName = $this->getShortClassName($arg, $type);
                $propertyName = lcfirst($shortClassName);
                $methodArguments[] = $shortInterfaceName." $".$propertyName;
                $readModelArguments[] = "$".$propertyName;
            } elseif (strpos($type, "\\")) {
                $this->addUseStatement($type);
                $classNameArray = explode("\\", $type);
                $type = array_pop($classNameArray);
                $propertyName = lcfirst(str_replace(["Interface", "interface"], "", $type));
                $methodArguments[] = $type." $".$propertyName;
                $readModelArguments[] = "$".$propertyName;
            }  else {
                $methodArguments[] = $type." $".$this->underscoreAndHyphenToCamelCase($arg);
                $readModelArguments[] = "$".$this->underscoreAndHyphenToCamelCase($arg);
            }
        }
        $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];

        if (is_array($returnType)) {
            $name = key($returnType);
            $returnType = $returnType[$name];
        }
        $methodLogic = "";

        if ($returnType === DataTypeInterface::DATA_TYPE_VOID) {
            $methodTemplate = self::METHOD_TEMPLATE_TYPE_VOID;
            $return = '';
        } elseif (
            $returnType === DataTypeInterface::STRUCTURE_TYPE_ENTITY ||
            $returnType === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
        ) {
            $this->addUseStatement($this->getClassName($this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL], $returnType));
            $shortClassName = $this->getShortClassName($this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL], $returnType);
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
        $addVar["readModelArguments"] = implode("", $readModelArguments);
        $addVar["readModelStoreMethodName"] = $this->getReadModelMethodName($methodName);
        $methodComment = sprintf("%s %s ReadModel in Storage.", ucfirst($methodName), $shortClassName);
        $methodTemplate = self::METHOD_TEMPLATE_TYPE_READ_MODEL;
        
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

    public function renderGetMethod(): string
    {
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN])) {
            $readModelName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL];
            $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN] = $readModelName;
        }
        $this->addUseStatement($this->getValueObjectClassName(self::VALUE_OBJECT_UNIQUE_UUID));
        $name = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];
        $returnType = $structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN];
        $shortClassName = $this->getShortClassName($name, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $returnType = $shortClassName;
        $return = "$".lcfirst($shortClassName);
        $addVar = [
            "readModelName" => ucfirst($this->underscoreAndHyphenToCamelCase($this->structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL])),
            "entityName" => ucfirst($this->underscoreAndHyphenToCamelCase($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])),
        ];

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FIND_BY_UUID,
            "",
            "get",
            "",
            $returnType,
            "",
            $return,
            $addVar
        );
    }

    protected function getReadModelMethodName(string $methodName): ?string
    {
        foreach (self::READ_MODEL_STORE_METHOD_KEYS as $readModelStoreMethodName => $keys) {
            foreach ($keys as $key) {
                if (strpos($methodName, $key) !== false) {
                    return $readModelStoreMethodName;
                }
            }
        }
        
        return null;
    }
}
