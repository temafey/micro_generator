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
class ValueObjectInterfaceGenerator extends AbstractGenerator
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
        $extends = "CommonValueObjectFactoryInterface";
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->addUseStatement("MicroModule\Base\Domain\Factory\CommonValueObjectFactoryInterface");

        foreach ($this->structure as $name => $valueObject) {
            if ($this->useCommonComponent && in_array($name, self::COMMON_VALUE_OBJECT_KEYS)) {
                continue;
            }
            $methods[] = $this->renderValueObjectMethod($name, $valueObject);
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
        );
    }

    protected function renderValueObjectMethod(string $valueObjectName, array $structure): string
    {
        $shortValueObjectClassName = $this->getShortClassName($valueObjectName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);

        if (!in_array($valueObjectName, self::UNIQUE_KEYS)) {
            $this->addUseStatement($this->getClassName($valueObjectName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        }
        $methodComment = sprintf("Create %s ValueObject.", $shortValueObjectClassName);
        $methodName = sprintf("make%s", $shortValueObjectClassName);
        $shortClassName = $this->getShortClassName($valueObjectName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $propertyType = $this->getValueObjectScalarType($structure['type']);
        $propertyName = lcfirst($shortClassName);
        $methodArgument = $propertyType." $".$propertyName;

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            $methodArgument,
            $shortValueObjectClassName
        );
    }
}
