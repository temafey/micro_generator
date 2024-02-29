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
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");
        $implements[] = $shortClassName."Interface";
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL));
        $this->addUseStatement($this->getInterfaceName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL));
        $this->addUseStatement($this->getInterfaceName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);
        $this->additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $this->additionalVariables['shortReadModelName'] = $this->getShortClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $this->additionalVariables['shortEntityNameInterfaceName'] = $this->getShortInterfaceName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FACTORY_READ_MODEL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }
}
