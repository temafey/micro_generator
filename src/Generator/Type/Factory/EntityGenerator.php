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
class EntityGenerator extends AbstractGenerator
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
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Payload");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $implements[] = $shortClassName."Interface";

        foreach ($this->structure as $name => $structure) {
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $methods[] = $this->renderCreateInstanceMethod($name);
            $methods[] = $this->renderMakeActualInstanceMethod($name);
        }

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FACTORY_ENTITY,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderCreateInstanceMethod(string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['entityName'] = ucfirst($this->underscoreAndHyphenToCamelCase($entityName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);
        $additionalVariables['shortEntityName'] = $shortEntityClassName;
        $additionalVariables['shortEntityInterfaceName'] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY_CREATE_INSTANCE,
            "",
            "",
            "",
            $shortEntityClassName,
            "",
            "",
            $additionalVariables
        );
    }

    protected function renderMakeActualInstanceMethod(string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['entityName'] = ucfirst($this->underscoreAndHyphenToCamelCase($entityName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);$this->additionalVariables['shortEntityName'] = $shortEntityClassName;
        $additionalVariables['shortEntityInterfaceName'] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY_MAKE_ACTUAL_INSTANCE,
            "",
            "",
            "",
            $shortEntityClassName,
            "",
            "",
            $additionalVariables
        );
    }
}
