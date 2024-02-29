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
class DtoGenerator extends AbstractGenerator
{

    /**
     * Use common abstract component.
     */
    protected bool $useCommonComponent = false;

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

        if ($this->useCommonComponent) {
            $this->addUseStatement("MicroModule\Common\Application\Dto\AbstractDto");
            $extends = "AbstractDto";
        } else {
            $this->addUseStatement($this->getInterfaceName($this->name, DataTypeInterface::STRUCTURE_TYPE_DTO));
            $implements[] = $this->getShortInterfaceName($this->name, DataTypeInterface::STRUCTURE_TYPE_DTO);
            $extends = "";
        }

        foreach ($this->structure as $arg) {
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg])) {
                throw new Exception(sprintf("Value object for dto '%s' was not found!", $arg));
            }
            $renderedMethod = $this->renderStructureMethod($arg);

            if (null === $renderedMethod) {
                continue;
            }
            $methods[] = $renderedMethod;
        }

        if (!empty($this->constructArgumentsAssignment)) {
            $methodLogic = implode("", $this->constructArgumentsAssignment);
            array_unshift(
                $methods, $this->renderMethod(
                    self::METHOD_TEMPLATE_TYPE_DEFAULT,
                    "Constructor",
                    "__construct",
                    implode(", ", $this->constructArguments),
                    "",
                    $methodLogic,
                    ""
                )
            );
        }

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

    public function renderStructureMethod(string $arg, array $addVar = []): ?string
    {
        $shortClassName = $this->getValueObjectShortClassName($arg);
        $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]["type"];
        $propertyType = $this->getValueObjectScalarType($valueObjectType);
        $propertyName = lcfirst($shortClassName);
        $this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        $propertyComment = sprintf("%s value object.", $shortClassName);
        $methodComment = sprintf("Return %s.", $propertyType);
        $this->constructArguments[] = $propertyType." $".$propertyName;

        if ($this->useCommonComponent && in_array($arg, self::UNIQUE_KEYS)) {
            return null;
        }
        $this->addProperty($propertyName, $propertyType, $propertyComment);
        $methodName = "get".$shortClassName;

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            $propertyType,
            "",
            "\$this->".$propertyName,
            $addVar
        );
    }
}
