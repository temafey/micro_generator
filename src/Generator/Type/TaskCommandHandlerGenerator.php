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
class TaskCommandHandlerGenerator extends AbstractGenerator
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
            $this->addUseStatement("MicroModule\Base\Application\CommandHandler\CommandHandlerInterface");
            $this->addUseStatement("MicroModule\Base\Domain\Command\CommandInterface");
        } else {
            $this->addUseStatement($classNamespace."\\"."CommandHandlerInterface");
        }
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag");

        $extends = "";
        $implements[] = "CommandHandlerInterface";
        $this->addUseStatement($this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK));
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
            $this->getClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK),
            "command.".lcfirst($this->domainName)
        );
        
        return $attributes;
    }

    protected function renderConstructMethod(): string
    {
        $className = $this->getInterfaceName(
            $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], 
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK
        );
        $this->addUseStatement($className);
        $shortClassName = $this->getShortInterfaceName(
            $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], 
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK
        );
        $propertyName = lcfirst($this->getShortClassName(
            $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY],
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK)
        );
        //$propertyComment = sprintf("%s object.", ucfirst($propertyName));
        //$this->addProperty($propertyName, $shortClassName, $propertyComment);
        $this->constructArguments[] = "protected ".$shortClassName." $".$propertyName;
        //$this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);

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
        $commandShortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK);
        $commandPropertyName = lcfirst($commandShortClassName);
        $repositoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY], DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
        $propertyName = lcfirst($this->getShortClassName(
            $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY],
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK)
        );
        $valueObjects = [];
        $methodBody = "";

        foreach ($commandStructure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $valueObjects[] = sprintf("\$%s->get%s()", $commandPropertyName, $shortClassName);
        }
        $methodBody .= sprintf("\r\n\t\t\$this->%s->add%sTask(%s);", $propertyName, ucfirst($this->underscoreAndHyphenToCamelCase($this->name)), implode(", ", $valueObjects));

        if ($this->useCommonComponent) {
            $methodComment = sprintf("Handle %s command.\r\n\t *\r\n\t * @param CommandInterface|%s $%s", $commandShortClassName, $commandShortClassName, $commandPropertyName);
            $commandShortArgumentClassName = "CommandInterface";
        } else {
            $methodComment = sprintf("Handle %s command.", $commandShortClassName);
            $commandShortArgumentClassName= $commandShortClassName;
        }
        
        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            "handle",
            $commandShortArgumentClassName." $".$commandPropertyName,
            "bool",
            $methodBody,
            "true"
        );
    }
}
