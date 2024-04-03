<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Helper\ReturnTypeNotFoundException;
use Exception;
use ReflectionException;
use Symfony\Component\VarDumper\Cloner\Data;

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
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $extends = "Projector";
        $attributes = ["#[AutoconfigureTag(name: 'broadway.domain.event_listener')]"];

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
            $constructArgument = sprintf("%s\n\t\tprotected %s $%s", $autowiring, $propertyType, $propertyName);
            $this->constructArguments[] = $constructArgument;
            //$propertyComment = sprintf("%s %s.", $shortClassName, $type);
            //$this->addProperty($propertyName, $shortClassName, $propertyComment);
        }
        $methods[] = $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
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
            $methods,
            [],
            $attributes
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
            $this->addUseStatement($this->getClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT));
            $eventShortName = $this->getShortClassName($event, DataTypeInterface::STRUCTURE_TYPE_EVENT);
            $methodName = sprintf("apply%s", $eventShortName);
            $methodComment = sprintf("Apply %s event.", $eventShortName);
            $methodArguments = sprintf("%s \$event", $eventShortName);
            $methodLogic = "\r\n\t\t\$entity = \$this->entityStore->get(\$event->getUuid());";
            $readModelRepositoryMethodName = $this->getReadModelRepositoryMethodName($event);
            $methodLogic .= sprintf("\r\n\t\t\$readModel = \$this->readModelFactory->make%sActualInstanceByEntity(\$entity);", ucfirst($this->underscoreAndHyphenToCamelCase($this->name)));
            $methodLogic .= sprintf("\r\n\t\t\$this->readModelStore->%s(\$readModel);", $readModelRepositoryMethodName);
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
