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
        $methods = [];
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");

        foreach ($this->structure as $name => $structure) {
            $entityName = $structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $name;
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $this->addUseStatement($this->getInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL));
            $methods[] = $this->renderCreateInstanceMethod($name, $entityName);
            $methods[] = $this->renderMakeActualInstanceMethod($name, $entityName);
        }

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_READ_MODEL_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }

    protected function renderCreateInstanceMethod(string $modelName, string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $shortEntityInterfaceName = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['modelName'] = ucfirst($this->underscoreAndHyphenToCamelCase($modelName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);
        $additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortEntityNameInterfaceName'] = lcfirst($shortEntityInterfaceName);
        $additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        

        return $this->renderMethodInterface(
            "",
            "",
            "",
            $additionalVariables['shortReadModelInterfaceName'],
            self::METHOD_TEMPLATE_TYPE_FACTORY_MODEL_CREATE_INSTANCE_INTERFACE,
            $additionalVariables
        );
    }

    protected function renderMakeActualInstanceMethod(string $modelName, string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $shortEntityInterfaceName = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['modelName'] = ucfirst($this->underscoreAndHyphenToCamelCase($modelName));
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);
        $additionalVariables['shortEntityNameInterfaceName'] = $shortEntityInterfaceName;
        $additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortEntityName'] = lcfirst($shortEntityClassName);

        return $this->renderMethodInterface(
            "",
            "",
            "",
            $additionalVariables['shortReadModelInterfaceName'],
            self::METHOD_TEMPLATE_TYPE_FACTORY_MODEL_MAKE_ACTUAL_INSTANCE_INTERFACE,
            $additionalVariables
        );
    }
}
