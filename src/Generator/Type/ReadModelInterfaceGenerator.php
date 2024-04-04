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
class ReadModelInterfaceGenerator extends AbstractGenerator
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
        $additionalVariables = [];
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Common\Domain\ReadModel\ReadModelInterface");
        $this->addUseStatement("Exception");
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
    
        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name])) {
            throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
        }
        $readModelProperties = $this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] ?? $this->structure;
        $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $this->name;
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
        $additionalVariables["shortEntityInterfaceName"] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables["shortValueObjectName"] = $this->getValueObjectShortClassName($entityName);

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_READ_MODEL_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            "ReadModelInterface",
            $additionalVariables
        );
    }

    protected function renderGetMethod(string $valueObject): string
    {
        $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodName = "get".$shortClassName;
        $propertyComment = sprintf("%s value.", $valueObject);
        $methodComment = sprintf("Return %s value.", $valueObject);
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$valueObject]['type']);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            "",
            "?".$scalarType,
            "",
            ""
        );
    }

    protected function renderValueObjectGetMethod(string $valueObject): string
    {
        $this->addUseStatement(sprintf("%s;", $this->getClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)));
        $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodName = "get".$shortClassName;
        $methodComment = sprintf("Return %s value object.", $valueObject);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            "",
            "?".$shortClassName,
            "",
            ""
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
