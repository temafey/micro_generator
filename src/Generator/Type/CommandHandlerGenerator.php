<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

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
class CommandHandlerGenerator extends AbstractGenerator
{
    /**
     * Is create type.
     */
    protected $typeCreate = false;

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
        if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$this->name])) {
            throw new Exception(sprintf("Command for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for handler '%s' was not found!", $this->name));
        }
        if (!isset($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
            throw new Exception(sprintf("Repository for handler '%s' was not found!", $this->name));
        }
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);

        if ($this->useCommonComponent) {
            $this->addUseStatement("MicroModule\Common\Application\CommandHandler\CommandHandlerInterface");
            $this->addUseStatement("MicroModule\Base\Domain\Command\CommandInterface");
        } else {
            $this->addUseStatement($classNamespace."\\"."CommandHandlerInterface");
        }
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag");
        
        $extends = "";
        $implements[] = "CommandHandlerInterface";
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND));
        $methods[] = $this->renderConstructMethod();
        $methods[] = $this->renderHandleMethod();
        $attributes = $this->renderAutowiringAttributes();

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods,
            [],
            $attributes
        );
    }

    protected function renderAutowiringAttributes(): array
    {
        $attributes = [];
        $attributes[] = sprintf("#[AutoconfigureTag(%s: '%s', attributes: [\n\t'command' => '%s',\n\t'bus' => '%s'\n])]",
            "name",
            "tactician.handler",
            $this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND),
            "command.".lcfirst($this->domainName)
        );
        
        return $attributes;
    }

    protected function renderConstructMethod(): string
    {
        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $type => $arg) {
            if ($arg === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE) {
                $arg .= "-".$this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            }
            $className = ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)
                ? $this->getClassName($arg, $type)
                : $this->getInterfaceName($arg, $type);
            $this->addUseStatement($className);
            $shortClassName = ($type === DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)
                ? $this->getShortClassName($arg, $type)
                : $this->getShortInterfaceName($arg, $type);
            $propertyName = lcfirst($this->getShortClassName($arg, $type));
            $propertyComment = sprintf("%s object.", ucfirst($propertyName));
            //$this->addProperty($propertyName, $shortClassName, $propertyComment);
            $this->constructArguments[] = "protected ".$shortClassName." $".$propertyName;
            //$this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);

            if ($type === DataTypeInterface::STRUCTURE_TYPE_FACTORY) {
                $this->typeCreate = true;
            }
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
            "",
            implode("", $this->constructArgumentsAssignment),
            ""
        );
    }

    protected function renderHandleMethod(): string
    {
        $commandStructure = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$this->name];
        $commandShortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND);
        $commandPropertyName = lcfirst($commandShortClassName);
        $entityShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $repositoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY], DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
        $valueObjects = [];
        $methodBody = "";

        foreach ($commandStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if (!$this->typeCreate && $arg === DataTypeInterface::DATA_TYPE_UUID) {
                continue;
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $valueObjects[] = sprintf("\$%s->get%s()", $commandPropertyName, $shortClassName);
        }

        if ($this->typeCreate) {
            $factoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_FACTORY], DataTypeInterface::STRUCTURE_TYPE_FACTORY));
            $methodBody .= sprintf("\r\n\t\t\$%s = \$this->%s->create%sInstance(%s);", $entityShortName, $factoryShortName, $shortClassName, implode(", ", $valueObjects));
        } else {
            $methodBody .= sprintf("\r\n\t\t\$%s = \$this->%s->get(\$%s->getUuid());", $entityShortName, $repositoryShortName, $commandPropertyName);
            $methodBody .= sprintf("\r\n\t\t\$%s->%s(%s);", $entityShortName, $this->underscoreAndHyphenToCamelCase($this->name), implode(", ", $valueObjects));
        }
        $methodBody .= sprintf("\r\n\t\t\$this->%s->store(\$%s);", $repositoryShortName, $entityShortName);

        if ($this->useCommonComponent) {
            $methodComment = sprintf("Handle %s command.\r\n\t *\r\n\t * @param CommandInterface|%s $%s", $commandShortClassName, $commandShortClassName, $commandPropertyName);
            $commandShortClassName = "CommandInterface";
        } else {
            $methodComment = sprintf("Handle %s command.", $commandShortClassName);
        }
        $return = sprintf("\$%s->getUuid()->toString()", $entityShortName);
        
        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            "handle",
            $commandShortClassName." $".$commandPropertyName,
            "string",
            $methodBody,
            $return
        );
    }
}
