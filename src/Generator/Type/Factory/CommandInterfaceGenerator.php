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
class CommandInterfaceGenerator extends AbstractGenerator
{
    protected $commandConstants = [];

    protected $makeCommandsInstanceByType = [];

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
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");
        $this->addUseStatement("MicroModule\Base\Domain\Factory\CommandFactoryInterface as BaseCommandFactoryInterface");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Payload");
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $extends = "BaseCommandFactoryInterface";
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $command) {
            $methods[] = $this->renderCommandMethod($command);
        }
        $this->additionalVariables['commandConstants'] = "\r\n\t".implode("; \r\n\t", $this->commandConstants).";";

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_COMMAND_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
        );
    }

    protected function renderCommandMethod(array $structure): string
    {
        $commandName = $structure['name'];
        $shortCommandClassName = $this->getShortClassName($commandName, $structure['type']);
        $this->addUseStatement($this->getClassName($commandName, $structure['type']));
        $methodComment = sprintf("Create %s Command.", $shortCommandClassName);
        $methodArguments = [];
        $commandArguments = [];

        if ($structure['type'] === DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK) {
            $commandName .= "-task";
        }
        $commandConstant  = strtoupper(str_replace("-", "_", $commandName))."_COMMAND";
        $methodName = sprintf("make%s", $shortCommandClassName);
        $this->commandConstants[] = sprintf("public const %s = \"%s\"", $commandConstant, $shortCommandClassName);
        $this->makeCommandsInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $commandConstant, $shortCommandClassName);

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if (!in_array($arg, self::UNIQUE_KEYS)) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $propertyType." $".$propertyName;
        }
        $methodArguments[] = "?Payload \$payload = null";

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortCommandClassName
        );
    }
}
