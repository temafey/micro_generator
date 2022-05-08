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
        $this->addUseStatement($this->getClassName("event", DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->addUseStatement($this->getInterfaceName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_ENTITY_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }
}
