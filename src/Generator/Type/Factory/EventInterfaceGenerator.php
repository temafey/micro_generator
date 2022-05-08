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
class EventInterfaceGenerator extends AbstractGenerator
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
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));

        foreach ($this->structure as $name => $event) {
            $methods[] = $this->renderEventMethod($name, $event);
        }

        return $this->renderInterface(
            self::INTERFACE_TEMPLATE_TYPE_DEFAULT,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
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
            if (!in_array($arg, self::UNIQUE_KEYS)) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $shortClassName." $".$propertyName;
        }

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortEventClassName
        );
    }
}
