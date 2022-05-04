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
class ProjectorGenerator extends AbstractGenerator
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
        $this->addUseStatement("Broadway\ReadModel\Projector");
        $extends = "Projector";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg => $type) {
            if (is_string($arg)) {
                $className = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getInterfaceName($arg, $type) : $this->getClassName($arg, $type);
                $this->addUseStatement($className);
                $shortClassName = ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY) ? $this->getShortInterfaceName($arg, $type) : $this->getShortClassName($arg, $type);
                $propertyName = lcfirst($this->getShortClassName($arg, $type));
                $propertyComment = "";
            } else {
                $arg = $type;
                $this->addUseStatement($arg);
                $classNameArray = explode("\\", $arg);
                $shortClassName = array_pop($classNameArray);
                $propertyName = str_replace("Interface", "", lcfirst($shortClassName));
                $propertyComment = "";
            }
            $this->addProperty($propertyName, $shortClassName, $propertyComment);
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
        $methods = array_merge($methods, $this->renderApplyMethods());

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

    protected function renderApplyMethods(): array
    {
        $methods = [];
        //$repositoryShortName = lcfirst($this->getShortClassName($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY], DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));

        foreach ($this->structure[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $event) {
            $entity = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$event][DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            $entityShortName = lcfirst($this->getShortClassName($entity, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("apply%s", $eventShortName);
            $methodComment = sprintf("Apply %s event.", $eventShortName);
            $methodArguments = sprintf("%s \$event", $eventShortName);
            $repositoryShortName = lcfirst($this->getShortClassName($entity, DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
            $methodLogic = sprintf("\r\n\t\t\$%s = \$this->%s->get(\$event->getUuid());", $entityShortName, $repositoryShortName);
            $methodLogic .= sprintf("\r\n\t\t\$this->commandRepository->add(\$%s);", $entityShortName);

            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_VOID,
                $methodComment,
                $methodName,
                $methodArguments,
                "",
                $methodLogic,
                ""
            );
        }

        return $methods;
    }
}
