<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\ValueObjectNotFoundException;
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
        $implements = [];
        $useTraits = [];
        $methods = [];
        $extends = "";
        $additionalVariables = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $this->addUseStatement("MicroModule\ValueObject\ValueObjectInterface");
        $this->addUseStatement("MicroModule\Base\Domain\Exception\ValueObjectInvalidException");

        $this->addUseStatement("Doctrine\ORM\Mapping\Column");
        $this->addUseStatement("Doctrine\ORM\Mapping\Entity");
        $this->addUseStatement("Doctrine\ORM\Mapping\Id");
        $this->addUseStatement("Doctrine\ORM\Mapping\Table");
        $this->addUseStatement("Exception");
        $additionalVariables = [];
        $additionalVariables["tableName"] = str_replace("-", "_", $this->name);
        $implements[] = $shortClassName."Interface";

        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name])) {
            throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
        }
        $readModelProperties = $this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] ?? $this->structure;
        $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $this->name;
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $this->addUseStatement($this->getClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->addUseStatement($this->getInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $methods[] = $this->renderValueObjectGetMethod(self::KEY_UNIQUE_UUID);

        foreach ($readModelProperties as $property) {
            if ($property === self::KEY_UNIQUE_UUID) {
                continue;
            }
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$property])) {
                throw new ValueObjectNotFoundException(sprintf("Value object '%s' from read model '%s' not found!", $property, $this->name));
            }
            $methods[] = $this->renderGetMethod($property);
        }
        $methods[] = $this->renderAssembleFromValueObjectMethod($readModelProperties, $entityName);
        $methods[] = $this->renderToArrayMethod($readModelProperties);
        $additionalVariables["shortEntityInterfaceName"] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables["shortValueObjectName"] = $this->getValueObjectShortClassName($entityName);

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_READ_MODEL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods,
            $additionalVariables
        );
    }

    protected function renderValueObjectGetMethod(string $valueObject): string
    {
        $this->addUseStatement(sprintf("%s;", $this->getClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)));
        $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodName = "get".$shortClassName;
        $propertyComment = sprintf("%s value object.", $valueObject);
        $methodComment = sprintf("Return %s value object.", $valueObject);
        $propertyName = lcfirst($shortClassName);
        $defaultValue = DataTypeInterface::DATA_TYPE_NULL;
        $propertyComment = $this->renderOrmColumnType($valueObject, $defaultValue);
        $this->addProperty($propertyName, "?".$shortClassName, $propertyComment, $defaultValue, self::PROPERTY_TEMPLATE_TYPE_ANNOTATION);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            "?".$shortClassName,
            "",
            "\$this->".$propertyName
        );
    }

    protected function renderGetMethod(string $valueObject): string
    {
        $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodName = "get".$shortClassName;
        $methodComment = sprintf("Return %s value.", $valueObject);
        $propertyName = lcfirst($shortClassName);
        $defaultValue = DataTypeInterface::DATA_TYPE_NULL;

        if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]) {
            throw new Exception(sprintf("ValueObject '%s' in structure not found", $valueObject));
        }
        $propertyComment = $this->renderOrmColumnType($valueObject, $defaultValue);
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]['type']);
        $this->addProperty($propertyName, "?".$scalarType, $propertyComment, $defaultValue, self::PROPERTY_TEMPLATE_TYPE_ANNOTATION);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            "?".$scalarType,
            "",
            "\$this->".$propertyName
        );
    }

    protected function renderOrmColumnType(
        string $name,
        mixed $defaultValue = null
    ): string {
        $options = [
            "name",
            "type",
            "unique",
            "nullable",
            "length",
            "insertable",
            "generated",
            "scale",
            "precision",
            "columnDefinition",
            "enumType",
            "options" => ["default" => $defaultValue]
        ];
        $idFlag = false;
        $options = [];
        $options["name"] = $name;
        $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$name]['type'];
        $scalarType = $this->getValueObjectScalarType($valueObjectType);
        $options["type"] = ($scalarType === DataTypeInterface::DATA_SCALAR_TYPE_DATETIME)
            ? DataTypeInterface::DATA_ORM_TYPE_SCALAR_MAPPING[$valueObjectType]
            : DataTypeInterface::DATA_ORM_TYPE_SCALAR_MAPPING[$scalarType];
        
        if (
            $name === self::KEY_UNIQUE_PROCESS_UUID ||
            $name === self::KEY_UNIQUE_UUID ||
            strpos($name, self::KEY_UNIQUE_UUID) !== false
        ) {
            $type = "guid";
            $options["type"] = DataTypeInterface::DATA_ORM_TYPE_GUID;
            $this->addUseStatement("Doctrine\ORM\Mapping\GeneratedValue");
            //$this->addUseStatement("Doctrine\ORM\Mapping\CustomIdGenerator");
            //$this->addUseStatement("Ramsey\Uuid\Doctrine\UuidGenerator");
            //$this->addUseStatement("Ramsey\Uuid\Doctrine\UuidOrderedTimeGenerator");
        }
        
        if (in_array($name, self::UNIQUE_KEYS)) {
            $options["unique"] = true;
            $idFlag = true;
        }
        if ($defaultValue === null || $defaultValue === DataTypeInterface::DATA_TYPE_NULL) {
            $options["nullable"] = true;
        } else {
            $options["options"] = ["default" => $defaultValue];
        }
        $optionArray = [];

        foreach ($options as $key => $option) {
            if  (is_array($option)) {
                $optionArray[] = sprintf("%s : %s", $key, json_encode($option));
                continue;
            }
            $option = is_bool($option)
                ? ($option === true) ? "true" : "false"
                : "'$option'";
            $optionArray[] = sprintf("%s : %s", $key, $option);
        }
        $propertyAnnotation = "";

        if ($idFlag) {
            $propertyAnnotation = "#[Id]\n\t";
        }
        $propertyAnnotation .= sprintf("#[Column(\n\t\t%s\n\t)]", implode(",\n\t\t", $optionArray));

        if ($idFlag) {
            //$propertyAnnotation .= "\n\t#[GeneratedValue(strategy: \"CUSTOM\")]";
            //$propertyAnnotation .= "\n\t#[CustomIdGenerator(class: UuidOrderedTimeGenerator::class)]";
        }
        
        return $propertyAnnotation;
    }
    
    protected function renderAssembleFromValueObjectMethod(array $readModelProperties, string $entityName): string
    {
        $shortClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodLogic = sprintf("\r\n\t\tif (!\$valueObject instanceof %s) {", $shortClassName);
        $methodLogic .= sprintf("\r\n\t\t\tthrow new ValueObjectInvalidException('%sEntity can be assembled only with %s value object');", $shortClassName, $shortClassName);
        $methodLogic .= "\r\n\t\t}";
        $methodLogic .= "\r\n\t\t\$this->uuid = \$uuid;";
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];

        foreach ($readModelProperties as $property) {
            if ($property === self::KEY_UNIQUE_UUID) {
                continue;
            }
            $shortClassName = $this->getShortClassName($property, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $varName = lcfirst($shortClassName);
            $methodLogic .= sprintf("\r\n\t\t\$this->%s = \$valueObject->%s()?->toNative();", $varName, $methodName);
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_VOID,
            "Assemble entity from value object.",
            "assembleFromValueObject",
            "ValueObjectInterface \$valueObject, ?Uuid \$uuid",
            "",
            $methodLogic,
            ""
        );
    }

    protected function renderToArrayMethod(array $readModelProperties): string
    {
        $methodLogic = "\r\n\t\t\$data = [];";

        foreach ($readModelProperties as $property) {
            /*if ($valueObject === self::KEY_UNIQUE_UUID) {
                continue;
            }*/
            $shortClassName = $this->getShortClassName($property, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            //$methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$this->%s) {", $propertyName);
            $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$property]['type'];
            $scalarType = $this->getValueObjectScalarType($valueObjectType);
            $ormType = ($scalarType === DataTypeInterface::DATA_SCALAR_TYPE_DATETIME)
                ? DataTypeInterface::DATA_ORM_TYPE_SCALAR_MAPPING[$valueObjectType]
                : DataTypeInterface::DATA_ORM_TYPE_SCALAR_MAPPING[$scalarType];
            
            if ($scalarType === DataTypeInterface::DATA_SCALAR_TYPE_DATETIME) {
                switch ($ormType) {
                    case DataTypeInterface::DATA_ORM_TYPE_DATETIME:
                        $dateFormat = "\DateTimeInterface::ATOM";
                        break;

                    case DataTypeInterface::DATA_ORM_TYPE_DATE:
                        $dateFormat = "\"Y-m-d\"";
                        break;
                        
                    case DataTypeInterface::DATA_ORM_TYPE_TIME:
                        $dateFormat = "\"H:i:s\"";
                        break;
                        
                    default:
                        throw new Exception(sprintf("Unknow orm date type '%s'!", $ormType));
                        break;
                }
                $methodLogic .= sprintf("\r\n\t\t\$data[\"%s\"] = \$this->%s?->format(%s);", $property, $propertyName, $dateFormat);
            } else {
                $methodLogic .= sprintf("\r\n\t\t\$data[\"%s\"] = \$this->%s;", $property, $propertyName);
            }
            //$methodLogic .= "\r\n\t\t}";
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Convert entity object to array.",
            "toArray",
            "",
            "array",
            $methodLogic,
            "\$data"
        );
    }

    /**
     * Return main entity short class name.
     */
    protected function getEntityName(): string
    {
        return $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
    }

    /**
     * Return main entity short class name.
     */
    protected function getEntitValueObjectClassName(): string
    {
        return $this->getValueObjectShortClassName($this->name);
    }
}
