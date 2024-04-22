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
class SagaGenerator extends AbstractGenerator
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
        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_EVENT])) {
            throw new Exception(sprintf("Entity for query '%s' was not found!", $this->name));
        }
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $this->addUseStatement("Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface");
        $this->addUseStatement("Broadway\Saga\State");
        $this->addUseStatement("MicroModule\Saga\AbstractSaga");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        //$this->addUseStatement($this->getInterfaceName("Command", DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $extends = "AbstractSaga";
        $implements[] = "StaticallyConfiguredSagaInterface";
        $this->properties[] = "\r\n\tprotected const STATE_CRITERIA_KEY = 'processId';";
        $this->properties[] = "\r\n\tprotected const STATE_ID_KEY = 'id';";
        $attributes = [];
        $attributes[] = sprintf("#[AutoconfigureTag(name: 'broadway.saga', attributes: ['type' => 'api.%s.%s'])]", $this->camelCaseToUnderscore($this->domainName), $this->name);

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if (is_string($arg)) {
                $className = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getInterfaceName($arg, $type) : $this->getClassName($arg, $type);
                $this->addUseStatement($className);
                $propertyType = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getShortInterfaceName($arg, $type) : $this->getShortClassName($arg, $type);
                $propertyName = $type;
                $autowiring = $this->getAutowiringName($className);
            } elseif (strpos("\\", $type) !== false) {
                $arg = $type;
                $this->addUseStatement($arg);
                $classNameArray = explode("\\", $arg);
                $propertyType = array_pop($classNameArray);
                $propertyName = str_replace("Interface", "", lcfirst($propertyType));
                $autowiring = $this->getAutowiringName($arg);
            } else {
                $classNameInterface = $this->getInterfaceName($this->name, $type);

                if ($type === DataTypeInterface::STRUCTURE_TYPE_COMMAND_BUS) {
                    $aliasShortClassName = ucfirst($type);
                    $this->addUseStatement($classNameInterface);
                } else {
                    $aliasShortClassName = (isset(DataTypeInterface::STRUCTURE_REPOSITORY_DATA_TYPES_MAPPING[$type]))
                        ? ucfirst(DataTypeInterface::STRUCTURE_REPOSITORY_DATA_TYPES_MAPPING[$type]) . "Interface"
                        : ucfirst($type) . "Interface";
                    $this->addUseStatement($classNameInterface . " as " . $aliasShortClassName);
                }
                $propertyType = $aliasShortClassName;
                $propertyName = $type;
                $autowiring = $this->getAutowiringServiceName($this->name, $type);
            }
            //$this->addProperty($propertyName, $shortClassName);
            $constructArgument = sprintf("%s\n\t\tprotected %s $%s", $autowiring, $propertyType, $propertyName);
            $this->constructArguments[] = $constructArgument;
            //$this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
            "",
            implode("", $this->constructArgumentsAssignment),
            "",
        );
        $methods[] = $this->renderConfigurationMethod();
        $this->renderHandleMethods($methods);

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

    protected function renderConfigurationMethod(): string
    {
        $criteria = [];
        $first = false;

        foreach ($this->structure[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $event => $command) {
            $shortClassName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $sagaStateSearchCriteria = sprintf("\r\n\t\t\t'%s' => static function(%s \$event) {", $shortClassName, $shortClassName);

            if (!$first) {
                $sagaStateSearchCriteria .= "\r\n\t\t\t\treturn null; // no criteria, start of a new saga";
                $first = true;
            } else {
                $sagaStateSearchCriteria .= "\r\n\t\t\t\treturn new State\Criteria([self::STATE_CRITERIA_KEY => \$event->getProcessUuid()->toNative()]);";
            }
            $sagaStateSearchCriteria .= "\r\n\t\t\t}";
            $criteria[] = $sagaStateSearchCriteria;
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            'Saga configuration method, return map of events and state search criteria.',
            "configuration",
            "",
            "",
            "",
            "[".implode(",", $criteria)."\r\n\t\t]"
        );
    }

    protected function renderHandleMethods(array &$methods): void
    {
        $shortCommandFactoryInterfaceName = $this->getShortInterfaceName("Command", DataTypeInterface::STRUCTURE_TYPE_FACTORY);

        foreach ($this->structure[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $event => $command) {
            $this->addUseStatement($this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("handle%s", $eventShortName);
            $methodComment = sprintf("Handle %s event.", $eventShortName);
            $methodArguments = sprintf("State \$state, %s \$event", $eventShortName);
            $methodLogic = "\r\n\t\t\$state->set(self::STATE_CRITERIA_KEY, (string) \$event->getProcessUuid());";
            $methodLogic .= "\r\n\t\t\$state->set(self::STATE_ID_KEY, (string) \$event->getUuid());";

            if (true === $command) {
                $methodLogic .= "\r\n\t\t\$state->setDone();";
            } else {
                $commandFactoryConst = $this->getCommandFactoryConst($command);
                $commandArguments = $this->getCommandArguments($command);
                $methodLogic .= sprintf(
                    "\r\n\t\t\$command = \$this->commandFactory->makeCommandInstanceByType(%s::%s, %s);",
                    $shortCommandFactoryInterfaceName,
                    $commandFactoryConst,
                    "\r\n\t\t\t".implode(",\r\n\t\t\t", $commandArguments)."\r\n\t\t"
                );
                $methodLogic .= "\r\n\t\t\$this->commandBus->handle(\$command);";
            }
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                $methodName,
                $methodArguments,
                "State",
                $methodLogic,
                "\$state"
            );
        }
    }

    /**
     * Generate command factory constant.
     */
    protected function getCommandArguments(string $command): array
    {
        $commandGetArgs = [];
        $commandArgs = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$command][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS];

        foreach ($commandArgs as $arg) {
            $shortClassName = $this->getValueObjectShortClassName($arg);
            $methodName = "get".$shortClassName;
            $commandGetArgs[] = sprintf("\$event->%s()->toNative()", $methodName);
        }

        return $commandGetArgs;
    }
}
