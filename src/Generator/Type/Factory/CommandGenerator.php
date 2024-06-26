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
class CommandGenerator extends AbstractGenerator
{
    protected $allowedMethods = [];

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
        $extends = "";
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $this->addUseStatement("MicroModule\Base\Domain\Command\CommandInterface as BaseCommandInterface");
        $this->addUseStatement("MicroModule\Base\Domain\Exception\FactoryException");
        $this->addUseStatement("MicroModule\Base\Domain\Dto\DtoInterface");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\ProcessUuid");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Id");
        $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Payload");
        $implements[] = $shortClassName."Interface";
        $this->addUseStatement($this->getClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $command) {
            $methods[] = $this->renderCommandMethod($command);
        }
        $this->additionalVariables['allowedCommands'] = implode(", \r\n\t\t", $this->allowedMethods).",";
        $this->additionalVariables['makeCommandsInstanceByType'] = implode(", \r\n\t\t\t", $this->makeCommandsInstanceByType).",";

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FACTORY_COMMAND,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderCommandMethod(array $structure): string
    {
        $commandName = $structure['name'];
        $entityName = $structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
        $shortCommandClassName = $this->getShortClassName($commandName, $structure['type']);
        $this->addUseStatement($this->getClassName($commandName, $structure['type']));
        $methodComment = sprintf("Create %s Command.", $shortCommandClassName);
        $methodArguments = [];
        $commandArguments = [];
        $additionalVariables = [];
        $methodLogic = "";

        if ($structure['type'] === DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK) {
            $commandName .= "-task";
        }
        $commandConstant  = strtoupper(str_replace("-", "_", $commandName))."_COMMAND";
        $methodName = sprintf("make%s", $shortCommandClassName);
        $this->allowedMethods[] = sprintf("self::%s", $commandConstant);
        $this->makeCommandsInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $commandConstant, $shortCommandClassName);

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if (!in_array($arg, self::UNIQUE_KEYS)) {
                $this->addUseStatement($this->getValueObjectClassName($arg));
            }
            $shortClassName = $this->getValueObjectShortClassName($arg);

            if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]) {
                throw new Exception(sprintf("Argument '%s' in ValueObjects structure not found!", $arg));
            }
            $valueObjectStructure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg];
            $propertyType = $this->getValueObjectScalarType($valueObjectStructure['type']);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $propertyType." $".$propertyName;
            $commandArguments[] = sprintf("%s::fromNative($%s)", $shortClassName, $propertyName);

            if (
                $structure['type'] === DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK ||
                $valueObjectStructure['type'] !== DataTypeInterface::VALUE_OBJECT_TYPE_ENTITY
            ) {
                continue;
            }
            if (
                $this->isCreateEntityMethodName($commandName) &&
                in_array(
                    self::KEY_CREATED_AT,
                    $valueObjectStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS]
                )
            ) {
                $methodLogic .= sprintf("\r\n\t\t\$%s[\"created_at\"] = \$%s[\"created_at\"] ?? date_create(\"now\");", $propertyName, $propertyName);
            }
            if (in_array(
                self::KEY_UPDATED_AT,
                $valueObjectStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS]
            )) {
                $methodLogic .= sprintf("\r\n\t\t\$%s[\"updated_at\"] = \$%s[\"updated_at\"] ?? date_create(\"now\");", $propertyName, $propertyName);
            }
        }
        $methodArguments[] = "?array \$payload = null";
        $commandArguments[] = "\$payload ? Payload::fromNative(\$payload) : null";
        $additionalVariables["shortFactoryClassName"] = $shortCommandClassName;
        $additionalVariables["factoryArguments"] = implode(", \r\n\t\t\t", $commandArguments);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortCommandClassName,
            $methodLogic,
            "",
            $additionalVariables
        );
    }
}
