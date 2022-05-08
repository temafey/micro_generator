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
class ValueObjectGenerator extends AbstractGenerator
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
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");
        $implements[] = $shortClassName."Interface";
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));

        foreach ($this->structure as $name => $valueObject) {
            $methods[] = $this->renderValueObjectMethod($name, $valueObject);
        }

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_DEFAULT,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
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
        $return = sprintf("%s::fromNative($%s)", $shortClassName, $propertyName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            $methodArgument,
            $shortValueObjectClassName,
            "",
            $return
        );
    }
}
