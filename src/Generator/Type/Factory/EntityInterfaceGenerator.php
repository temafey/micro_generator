<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type\Factory;

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
class EntityInterfaceGenerator extends AbstractGenerator
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
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Payload");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");

        foreach ($this->structure as $name => $structure) {
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $methods[] = $this->renderCreateInstanceMethod($name);
            $methods[] = $this->renderMakeActualInstanceMethod($name);
        }
        
        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_ENTITY_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }

    protected function renderCreateInstanceMethod(string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['entityName'] = ucfirst($this->underscoreAndHyphenToCamelCase($entityName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);
        $additionalVariables['shortEntityName'] = $shortEntityClassName;
        $shortEntityInterfaceName = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethodInterface(
            "",
            "",
            "",
            $shortEntityInterfaceName,
            self::METHOD_TEMPLATE_TYPE_FACTORY_CREATE_INSTANCE_INTERFACE,
            $additionalVariables
        );
    }

    protected function renderMakeActualInstanceMethod(string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['entityName'] = ucfirst($this->underscoreAndHyphenToCamelCase($entityName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);
        $additionalVariables['shortEntityName'] = $shortEntityClassName;
        $shortEntityInterfaceName = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethodInterface(
            "",
            "",
            "",
            $shortEntityInterfaceName,
            self::METHOD_TEMPLATE_TYPE_FACTORY_MAKE_ACTUAL_INSTANCE_INTERFACE,
            $additionalVariables
        );
    }
}
