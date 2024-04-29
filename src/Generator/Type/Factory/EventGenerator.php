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
class EventGenerator extends AbstractGenerator
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
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Payload");
        $implements[] = $shortClassName."Interface";
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $name => $event) {
            $methods[] = $this->renderEventMethod($name, $event);
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

    protected function renderEventMethod(string $eventName, array $structure): string
    {
        $shortEventClassName = $this->getShortClassName($eventName, DataTypeInterface::STRUCTURE_TYPE_EVENT);
        $this->addUseStatement($this->getClassName($eventName, DataTypeInterface::STRUCTURE_TYPE_EVENT));
        $methodComment = sprintf("Create %s Event.", $shortEventClassName);
        $methodArguments = [];
        $commandArguments = [];
        $additionalVariables = [];
        $methodName = sprintf("make%s", $shortEventClassName);

        foreach ($structure as $arg) {
            if (
                $arg === self::KEY_UNIQUE_ID ||
                !in_array($arg, self::UNIQUE_KEYS)
            ) {
                $this->addUseStatement($this->getValueObjectClassName($arg));
            }
            $shortClassName = $this->getValueObjectShortClassName($arg);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $shortClassName." $".$propertyName;
            $commandArguments[] = "$".$propertyName;
        }
        $methodArguments[] = "?Payload \$payload = null";
        $commandArguments[] = "\$payload";
        $additionalVariables["shortFactoryClassName"] = $shortEventClassName;
        $additionalVariables["factoryArguments"] = implode(", ", $commandArguments);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortEventClassName,
            "",
            "",
            $additionalVariables
        );
    }
}
