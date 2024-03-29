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
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");

        $this->addUseStatement("Doctrine\ORM\Mapping\Column");
        $this->addUseStatement("Doctrine\ORM\Mapping\Entity");
        $this->addUseStatement("Doctrine\ORM\Mapping\Id");
        $this->addUseStatement("Doctrine\ORM\Mapping\Table");
        $additionalVariables = [];
        $additionalVariables["tableName"] = $this->name;
        $implements[] = $shortClassName."Interface";

        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name])) {
            throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
        }
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->addUseStatement($this->getInterfaceName($this->name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $methods[] = $this->renderValueObjectGetMethod(self::KEY_UNIQUE_UUID);

        foreach ($entityValueObject as $valueObject) {
            if ($valueObject === self::KEY_UNIQUE_UUID) {
                continue;
            }
            $methods[] = $this->renderGetMethod($valueObject);
        }
        $methods[] = $this->renderAssembleFromValueObjectMethod();
        $methods[] = $this->renderToArrayMethod();

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
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]['type']);
        $propertyComment = $this->renderOrmColumnType($valueObject, $scalarType, $defaultValue);
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
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]['type']);
        $propertyComment = $this->renderOrmColumnType($valueObject, $scalarType, $defaultValue);
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
        string $type, 
        mixed $defaultValue = null, 
        ?int $length = null
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
            "options" => ["default" => false]
        ];
        $idFlag = false;
        $options = [];
        $options["name"] = $name;
        $options["type"] = DataTypeInterface::DATA_ORM_TYPE_SCALAR_MAPPING[$type];
        
        if (
            $name === self::KEY_UNIQUE_PROCESS_UUID ||
            $name === self::KEY_UNIQUE_UUID
        ) {
            $type = "guid";
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
        
        return $propertyAnnotation;
    }
    
    protected function renderAssembleFromValueObjectMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodLogic = sprintf("\r\n\t\tif (!\$valueObject instanceof %s) {", $shortClassName);
        $methodLogic .= sprintf("\r\n\t\t\tthrow new ValueObjectInvalidException('%sEntity can be assembled only with %s value object');", $shortClassName, $shortClassName);
        $methodLogic .= "\r\n\t\t}";
        $methodLogic .= "\r\n\t\t\$this->uuid = \$uuid;";
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];

        foreach ($entityValueObject as $valueObject) {
            if ($valueObject === self::KEY_UNIQUE_UUID) {
                continue;
            }
            $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $varName = lcfirst($shortClassName);
            $methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$valueObject->%s()) {", $methodName);
            $methodLogic .= sprintf("\r\n\t\t\t\$this->%s = \$valueObject->%s()->toNative();", $varName, $methodName);
            $methodLogic .= "\r\n\t\t}";
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_VOID,
            "Assemble entity from value object.",
            "assembleFromValueObject",
            "ValueObjectInterface \$valueObject, Uuid \$uuid",
            "",
            $methodLogic,
            ""
        );
    }

    protected function renderToArrayMethod(): string
    {
        $methodLogic = "\r\n\t\t\$data = [];";
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        array_unshift($entityValueObject, self::KEY_UNIQUE_UUID);

        foreach ($entityValueObject as $valueObject) {
            /*if ($valueObject === self::KEY_UNIQUE_UUID) {
                continue;
            }*/
            $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            //$methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$this->%s) {", $propertyName);
            $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]['type']);
            
            if ($scalarType === DataTypeInterface::DATA_SCALAR_TYPE_DATETIME) {
                $propertyName .= "->format(\DateTime::ATOM)";
            }
            $methodLogic .= sprintf("\r\n\t\t\t\$data[\"%s\"] = \$this->%s;", $valueObject, $propertyName);
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
