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
    protected const READ_MODEL_REPOSITORY_METHOD_NAME_ADD = "add";
    protected const READ_MODEL_REPOSITORY_METHOD_NAME_UPDATE = "update";
    protected const READ_MODEL_REPOSITORY_METHOD_NAME_DELETE = "delete";

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
            } else {
                $arg = $type;
                $this->addUseStatement($arg);
                $classNameArray = explode("\\", $arg);
                $shortClassName = array_pop($classNameArray);
                $propertyName = str_replace("Interface", "", lcfirst($shortClassName));
            }
            $propertyComment = sprintf("%s %s.", $shortClassName, $type);
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
            if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$event]) {
                throw new Exception(sprintf("Event '%' not found!", $event));
            }
            $entity = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$event][DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            $entityShortName = lcfirst($this->getShortClassName($entity, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
            $this->addUseStatement($this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("apply%s", $eventShortName);
            $methodComment = sprintf("Apply %s event.", $eventShortName);
            $methodArguments = sprintf("%s \$event", $eventShortName);
            $repositoryShortName = lcfirst($this->getShortClassName($entity, DataTypeInterface::STRUCTURE_TYPE_REPOSITORY));
            $methodLogic = sprintf("\r\n\t\t\$%s = \$this->%s->get(\$event->getUuid());", $entityShortName, "entityStoreRepository");
            $readModelRepositoryMethodName = $this->getReadModelRepositoryMethodName($event);
            $methodLogic .= sprintf("\r\n\t\t\$readModel = \$this->readModelFactory->makeActualInstanceByEntity(\$%s);", $entityShortName);
            $methodLogic .= sprintf("\r\n\t\t\$this->readModelRepository->%s(\$readModel);", $readModelRepositoryMethodName);
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

    /**
     * Analize event name and return read model repository name.
     */
    protected function getReadModelRepositoryMethodName(string $eventName): string
    {
        $eventName = strtolower($eventName);

        if (str_contains($eventName, self::READ_MODEL_REPOSITORY_METHOD_NAME_ADD)) {
            $methodName = self::READ_MODEL_REPOSITORY_METHOD_NAME_ADD;
        } elseif (str_contains($eventName, self::READ_MODEL_REPOSITORY_METHOD_NAME_DELETE)) {
            $methodName = self::READ_MODEL_REPOSITORY_METHOD_NAME_DELETE;
        } else {
            $methodName = self::READ_MODEL_REPOSITORY_METHOD_NAME_UPDATE;
        }

        return $methodName;
    }
}
