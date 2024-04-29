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
        $this->addUseStatement("MicroModule\Base\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");
        $implements[] = $shortClassName."Interface";

        foreach ($this->structure as $name => $structure) {
            $entityName = $structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $name;
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL));
            $this->addUseStatement($this->getInterfaceName($name, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL));
            $this->addUseStatement($this->getInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $methods[] = $this->renderCreateInstanceMethod($name, $entityName);
            $methods[] = $this->renderMakeActualInstanceMethod($name, $entityName);
        }

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

    protected function renderCreateInstanceMethod(string $modelName, string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['modelName'] = ucfirst($this->underscoreAndHyphenToCamelCase($modelName));
        $additionalVariables['shortEntityName'] = lcfirst($shortEntityClassName);
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);
        $additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortReadModelName'] = $this->getShortClassName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortEntityNameInterfaceName'] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY_MODEL_CREATE_INSTANCE,
            "",
            "",
            "",
            $shortEntityClassName,
            "",
            "",
            $additionalVariables
        );
    }

    protected function renderMakeActualInstanceMethod(string $modelName, string $entityName): string
    {
        $shortEntityClassName = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
        $additionalVariables = [];
        $additionalVariables['modelName'] = ucfirst($this->underscoreAndHyphenToCamelCase($modelName));
        $additionalVariables['shortEntityName'] = lcfirst($shortEntityClassName);
        $additionalVariables['shortValueObjectName'] = $this->getShortClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $additionalVariables['propertyValueObjectName'] = lcfirst($additionalVariables['shortValueObjectName']);
        $additionalVariables['shortReadModelInterfaceName'] = $this->getShortInterfaceName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortReadModelName'] = $this->getShortClassName($modelName, DataTypeInterface::STRUCTURE_TYPE_READ_MODEL);
        $additionalVariables['shortEntityNameInterfaceName'] = $this->getShortInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY_MODEL_MAKE_ACTUAL_INSTANCE,
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
