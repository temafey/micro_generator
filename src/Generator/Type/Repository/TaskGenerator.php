<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type\Repository;

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
class TaskGenerator extends AbstractGenerator
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
        $extends = "";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($interfaceNamespace);
        $this->addUseStatement("MicroModule\Common\Domain\Exception\TaskException");
        $this->addUseStatement("Enqueue\Client\ProducerInterface");
        $this->addUseStatement("MicroModule\Task\Application\Processor\JobCommandBusProcessor");
        $this->addUseStatement($this->getInterfaceName($this->domainName."Command", DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $implements[] = $interfaceShortName;
        $addVar = [
            "TaskException" => "TaskException",
        ];

        foreach ($this->structure as $entityName => $commands) {
            foreach ($commands as $commandName => $command) {
                $methods[] = $this->renderStructureMethod($entityName, $commandName, $command, $addVar);
            }
        }

        return $this->renderClass(
            self::CLASS_TEMPLATE_REPOSITORY_TASK,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods,
            $addVar
        );
    }

    public function renderStructureMethod(string $entityName, string $commandName, array $structure, array $addVar = []): string
    {
        if (!isset($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS])) {
            throw new Exception(sprintf("Arguments for task repository method '%s' was not found!", $commandName));
        }
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for task repository method '%s' was not found!", $commandName));
        }
        $methodArguments = [];
        $commandArguments = [];

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $className = $this->getValueObjectClassName($arg);
            $this->addUseStatement($className);
            $shortClassName = $this->getValueObjectShortClassName($arg);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $shortClassName." $".$propertyName;
            $commandArguments[] = "$".$propertyName."->toNative()";
        }
        $methodLogic = "";
        $returnType = "";
        $return = "";
        $entityName = $structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
        $shortCommandFactoryInterfaceName = $this->getShortInterfaceName($this->domainName."Command", DataTypeInterface::STRUCTURE_TYPE_FACTORY);
        $addVar["factoryCommandName"] = sprintf("%s::%s", $shortCommandFactoryInterfaceName, $this->getCommandFactoryConst($commandName));
        $addVar["commandArguments"] = implode(",\n\t\t\t\t", $commandArguments);
        $commandName = ucfirst($this->underscoreAndHyphenToCamelCase($commandName));
        $methodComment = sprintf("Send `%s Command` into queue.", $commandName);
        $methodName = sprintf("add%sTask", $commandName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_TASK,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $returnType,
            $methodLogic,
            $return,
            $addVar
        );
    }
}
