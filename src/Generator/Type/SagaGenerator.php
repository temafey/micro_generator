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
        $extends = "AbstractSaga";
        $implements[] = "StaticallyConfiguredSagaInterface";
        $this->properties[] = "\r\n   protected const STATE_CRITERIA_KEY = 'processId';";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $this->addUseStatement($arg);
            $classNameArray = explode("\\", $arg);
            $shortClassName = array_pop($classNameArray);
            $propertyName = str_replace("Interface", "", lcfirst($shortClassName));
            $this->addProperty($propertyName, $shortClassName);
            $this->constructArguments[] = $shortClassName." $".$propertyName;
            $this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            implode(", ", $this->constructArguments),
            "",
            implode("", $this->constructArgumentsAssignment),
            ""
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
            $methods
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
        foreach ($this->structure[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $event => $command) {
            $this->addUseStatement($this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("handle%s", $eventShortName);
            $methodComment = sprintf("Handle %s event.", $eventShortName);
            $methodArguments = sprintf("State \$state, %s \$event", $eventShortName);
            $methodLogic = "\r\n\t\t\$state->set(self::STATE_CRITERIA_KEY, (string) \$event->getProcessUuid());";

            if (true === $command) {
                $methodLogic .= "\r\n\t\t\$state->setDone();";
            } else {
                $commandFactoryConst = $this->getCommandFactoryConst($command);
                $methodLogic .= sprintf("\r\n\t\t\$command = \$this->commandFactory->makeCommandInstanceByType(CommandFactory::%s, \$event->getUuid());", $commandFactoryConst);
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
}
