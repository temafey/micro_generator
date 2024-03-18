<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\CommandNotFoundException;
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
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $this->addUseStatement("Assert\Assertion");
        $this->addUseStatement("Broadway\EventSourcing\EventSourcedAggregateRoot");
        $this->addUseStatement("Broadway\Serializer\Serializable");
        $this->addUseStatement("MicroModule\Common\Domain\Exception\ValueObjectInvalidException");
        $this->addUseStatement("MicroModule\Snapshotting\EventSourcing\AggregateAssemblerInterface");
        $this->addUseStatement("MicroModule\Common\Domain\Entity\EntityInterface");
        $this->addUseStatement("MicroModule\Common\Domain\ValueObject\Payload");
        $this->addUseStatement("MicroModule\ValueObject\ValueObjectInterface");
        $this->addUseStatement("Broadway\Serializer\Serializable");
        $this->addUseStatement($this->getClassName("event", DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $this->addUseStatement($this->getInterfaceName("event", DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $implements[] = $shortClassName."Interface";
        $implements[] = "EntityInterface";
        $implements[] = "AggregateAssemblerInterface";
        $implements[] = "Serializable";
        $extends = "EventSourcedAggregateRoot";
        $this->addProperty("eventFactory", "EventFactoryInterface", "EventFactory object.");
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            "?EventFactoryInterface \$eventFactory = null",
            "",
            "\r\n\t\t\$this->eventFactory = \$eventFactory ?? new EventFactory();",
            ""
        );

        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name])) {
            throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
        }
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));

        if (!in_array(self::KEY_UNIQUE_UUID, $entityValueObject)) {
            array_unshift($entityValueObject, self::KEY_UNIQUE_UUID);
        }

        if (!in_array(self::KEY_UNIQUE_PROCESS_UUID, $entityValueObject)) {
            array_unshift($entityValueObject, self::KEY_UNIQUE_PROCESS_UUID);
        }

        foreach ($entityValueObject as $valueObject) {
            $methods[] = $this->renderValueObjectGetMethod($valueObject);
        }

        foreach ($this->structure as $command) {
            [$commandMethod, $applyMethods] = $this->renderCommandMethodandApplyMethods($command);
            $methods[] = $commandMethod;
            array_push($methods,  ...$applyMethods);
        }
        $methods[] = $this->renderCreateMethod();
        $methods[] = $this->renderCreateActualMethod();
        $methods[] = $this->renderDeserializeMethod();
        $methods[] = $this->renderAssembleFromValueObjectMethod();
        $methods[] = $this->renderAssembleToValueObjectMethod();
        $methods[] = $this->renderNormalizeMethod();

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_ENTITY,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderValueObjectGetMethod(string $valueObject): string
    {
        $className = $this->getValueObjectClassName($valueObject);
        $this->addUseStatement($className);
        $shortClassName = $this->getValueObjectShortClassName($valueObject);
        $propertyName = lcfirst($shortClassName);
        $methodName = "get".$shortClassName;
        $propertyComment = sprintf("%s value object.", $valueObject);
        $methodComment = sprintf("Return %s value object.", $valueObject);
        $defaultValue = DataTypeInterface::DATA_TYPE_NULL;
        $this->addProperty($propertyName, "?".$shortClassName, $propertyComment, $defaultValue);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            "?".$shortClassName,
            "",
            "\$this->".$propertyName
        );
    }

    protected function renderCommandMethodandApplyMethods(string $command): array
    {
        if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$command]) {
            throw new CommandNotFoundException(sprintf("Commands '%s' not found", $command));
        }
        $commandEvents = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$command][DataTypeInterface::STRUCTURE_TYPE_EVENT];
        $commandArgs = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$command][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $methodName = $command;
        $methodComment = sprintf("Execute %s command.", $command);
        $commandArguments = [];
        $commandProperties = [];
        $processUuidExists = false;
        $uuidExists = false;

        foreach ($commandArgs as $arg) {
            $shortClassName = $this->getValueObjectShortClassName($arg);
            $propertyName = lcfirst($shortClassName);

            if ($shortClassName === self::VALUE_OBJECT_UNIQUE_PROCESS_UUID) {
                $processUuidExists = true;
            }
            if ($arg === self::KEY_UNIQUE_UUID) {
                $uuidExists = true;
                $commandProperties[] = "\$this->".$propertyName;
                continue;
            }
            $commandProperties[] = "$".$propertyName;
            $commandArguments[] = $shortClassName." $".$propertyName;
        }
        if (!$uuidExists) {
            $uuid = "\$this->".self::KEY_UNIQUE_UUID;
            
            if ($processUuidExists) {
                $commandProperties = array_merge(array_slice($commandProperties, 0, 1), [$uuid], array_slice($commandProperties, 1));
            } else {
                array_unshift($commandProperties, $uuid);
            }
        }
        [$applyEvents, $applyMethods] = $this->renderApplyMethods($commandEvents, $commandProperties);
        $commandMethod = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_VOID,
            $methodComment,
            $methodName,
            implode(", ", $commandArguments),
            "",
            implode("", $applyEvents),
            ""
        );

        return [$commandMethod, $applyMethods];
    }

    protected function renderApplyMethods(array $events, array $commandProperties): array
    {
        $applyEvents = [];
        $applyMethods = [];

        foreach ($events as $event => $args) {
            $this->addUseStatement(sprintf("%s;", $this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT)));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("apply%s", $eventShortName);
            $methodComment = sprintf("Apply %s event.", $eventShortName);
            $methodArguments = sprintf("%s \$event", $eventShortName);
            $methodLogic = "";

            foreach ($args as $arg) {
                if (in_array($arg, self::UNIQUE_KEYS)) {
                    continue;
                }
                $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
                $getMethodName = "get".$shortClassName;
                $propertyName = lcfirst($shortClassName);

                if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg])) {
                    throw new Exception(sprintf("ValueObject '%s' for entity was not found!", $this->name));
                }
                $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg][DataTypeInterface::BUILDER_STRUCTURE_TYPE];
                $methodLogic .= ($valueObjectType === DataTypeInterface::VALUE_OBJECT_TYPE_ENTITY)
                    ? sprintf("\r\n\t\t\$this->assembleFromValueObject(\$event->%s());", $getMethodName)
                    : sprintf("\r\n\t\t\$this->%s = \$event->%s();", $propertyName, $getMethodName);
            }
            $applyMethods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_VOID,
                $methodComment,
                $methodName,
                $methodArguments,
                "",
                $methodLogic,
                ""
            );
            $applyEvents[] = sprintf("\r\n\t\t\$this->apply(\$this->eventFactory->make%s(%s));", $eventShortName, implode(", ", $commandProperties));
        }

        return [$applyEvents, $applyMethods];
    }

    protected function renderCreateMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodLogic = "\r\n\t\t\$entity = new static(\$eventFactory);";
        $firstCommand = $this->structure[0];
        $commandArgs = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$firstCommand][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        $commandProperties = [];
        $processUuidExists = false;
        $uuidExists = false;

        foreach ($commandArgs as $arg) {
            $shortClassName = $this->getValueObjectShortClassName($arg);
            $commandProperties[] = $shortClassName." $".lcfirst($shortClassName);
            
            if ($shortClassName === self::VALUE_OBJECT_UNIQUE_PROCESS_UUID) {
                $processUuidExists = true;
            }
            if ($shortClassName === self::VALUE_OBJECT_UNIQUE_UUID) {
                $uuidExists = true;
            }
        }
        $methodLogic .= "\r\n\t\t\$entity->uuid = \$uuid;";
        $firstMethodName = $this->underscoreAndHyphenToCamelCase($firstCommand);
        $methodLogic .= "\r\n\t\t".sprintf("\$entity->%s(\$processUuid, \$%s);", $firstMethodName, lcfirst($shortClassName));

        if (!$uuidExists) {
            $shortClassName = $this->getValueObjectShortClassName(self::KEY_UNIQUE_UUID);
            $argumentName = "$".lcfirst($shortClassName);
            $commandProperty = $shortClassName." $".lcfirst($shortClassName);

            if ($processUuidExists) {
                $commandProperties = array_merge(array_slice($commandProperties, 0, 1), [$commandProperty], array_slice($commandProperties, 1));
            } else {
                array_unshift($commandProperties, $commandProperty);
            }
        }
        $varName = implode(",\n\t\t", $commandProperties);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            sprintf('Factory method for creating a new %sEntity.', $shortClassName),
            "create",
            sprintf("\n\t\t%s,\n\t\t?EventFactoryInterface \$eventFactory = null\n\t", $varName),
            "self",
            $methodLogic,
            "\$entity"
        );
    }

    protected function renderCreateActualMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $varName = lcfirst($shortClassName);
        $methodLogic = "\r\n\t\t\$entity = new static(\$eventFactory);";
        $methodLogic .= "\r\n\t\t\$entity->uuid = \$uuid;";
        $methodLogic .= sprintf("\r\n\t\t\$entity->assembleFromValueObject(\$%s);", $varName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            sprintf('Factory method for creating a new %sEntity.', $shortClassName),
            "createActual",
            sprintf('Uuid $uuid, %s $%s, ?EventFactoryInterface $eventFactory = null', $shortClassName, $varName),
            "self",
            $methodLogic,
            "\$entity"
        );
    }

    protected function renderDeserializeMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $varName = lcfirst($shortClassName);
        $methodLogic = "\r\n\t\tAssertion::keyExists(\$data, self::KEY_UUID);";
        $methodLogic .= sprintf("\r\n\t\t\$%s = %s::fromNative(\$data);", $varName, $shortClassName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            sprintf('Factory method for creating a new %sEntity.', $shortClassName),
            "deserialize",
            "array \$data",
            "self",
            $methodLogic,
            sprintf("static::createActual(Uuid::fromNative(\$data[self::KEY_UUID]), \$%s)", $varName)
        );
    }

    protected function renderAssembleFromValueObjectMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodLogic = sprintf("\r\n\t\tif (!\$valueObject instanceof %s) {", $shortClassName);
        $methodLogic .= sprintf("\r\n\t\t\tthrow new ValueObjectInvalidException('%sEntity can be assembled only with %s value object');", $shortClassName, $shortClassName);
        $methodLogic .= "\r\n\t\t}";
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        array_unshift($entityValueObject, self::KEY_UNIQUE_UUID);
        array_unshift($entityValueObject, self::KEY_UNIQUE_PROCESS_UUID);

        foreach ($entityValueObject as $valueObject) {
            if (in_array($valueObject, self::UNIQUE_KEYS)) {
                continue;
            }
            $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $varName = lcfirst($shortClassName);
            $methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$valueObject->%s()) {", $methodName);
            $methodLogic .= sprintf("\r\n\t\t\t\$this->%s = \$valueObject->%s();", $varName, $methodName);
            $methodLogic .= "\r\n\t\t}";
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_VOID,
            "Assemble entity from value object.",
            "assembleFromValueObject",
            "ValueObjectInterface \$valueObject",
            "",
            $methodLogic,
            ""
        );
    }

    protected function renderAssembleToValueObjectMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $varName = lcfirst($shortClassName);
        $methodLogic = sprintf("\r\n\t\t\$%s = \$this->normalize();", $varName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Assemble value object from entity.",
            "assembleToValueObject",
            "",
            "ValueObjectInterface",
            $methodLogic,
            sprintf("%s::fromNative(\$%s)", $shortClassName, $varName)
        );
    }

    protected function renderNormalizeMethod(): string
    {
        $methodLogic = "\r\n\t\t\$data = [];";
        $entityValueObject = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$this->name][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];
        array_unshift($entityValueObject, self::KEY_UNIQUE_UUID);
        array_unshift($entityValueObject, self::KEY_UNIQUE_PROCESS_UUID);

        foreach ($entityValueObject as $valueObject) {
            $shortClassName = $this->getShortClassName($valueObject, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$this->%s()) {", $methodName);
            $methodLogic .= sprintf("\r\n\t\t\t\$data[\"%s\"] = \$this->%s()->toNative();", $valueObject, $methodName);
            $methodLogic .= "\r\n\t\t}";
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Convert entity object to array.",
            "normalize",
            "",
            "array",
            $methodLogic,
            "\$data"
        );
    }
}
