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
     * Render getter methods in dto.
     */
    protected bool $renderGetters = false;

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
        $methods[] = $this->renderDenormalizeMethod();
        $methods[] = $this->renderNormalizeMethod();

        if (!empty($this->constructArguments)) {
            $methodLogic = implode("", $this->constructArgumentsAssignment);
            array_unshift(
                $methods, $this->renderMethod(
                    static::METHOD_TEMPLATE_TYPE_DEFAULT,
                    "Constructor",
                    "__construct",
                "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
                    "",
                    $methodLogic,
                    ""
                )
            );
        }

        return $this->renderClass(
            static::CLASS_TEMPLATE_TYPE_FULL,
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
        $property = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
        $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]["type"];
        $propertyType = $this->getValueObjectScalarType($valueObjectType);
        $propertyName = lcfirst($property);
        $propertyComment = sprintf("%s %s value.", $property, $propertyType);
        $methodComment = sprintf("Return %s.", $propertyType);
        $this->constructArguments[] = "public readonly ?".$propertyType." $".$propertyName;

        if (!$this->renderGetters) {
            return null;
        }

        if ($this->useCommonComponent && in_array($arg, static::UNIQUE_KEYS)) {
            return null;
        }
        $methodName = "get".$property;

        return $this->renderMethod(
            static::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            $propertyType,
            "",
            "\$this->".$propertyName,
            $addVar
        );
    }

    protected function renderNormalizeMethod(): string
    {
        $methodLogic = "\r\n\t\t\$data = [];";

        foreach ($this->structure as $arg) {
            $propertyName = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
            $argConstant  = strtoupper(str_replace("-", "_", $arg));
            $methodLogic .= sprintf("\r\n\r\n\t\tif (null !== \$this->%s) {", $propertyName);
            $methodLogic .= sprintf("\r\n\t\t\t\$data[static::%s] = \$this->%s;", $argConstant, $propertyName);
            $methodLogic .= "\r\n\t\t}";
        }

        return $this->renderMethod(
            static::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Convert dto object to array.",
            "normalize",
            "",
            "array",
            $methodLogic,
            "\$data"
        );
    }

    protected function renderDenormalizeMethod(): string
    {
        $shortClassName = $this->getShortClassName($this->name, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $methodLogic = "";
        $args = [];

        foreach ($this->structure as $arg) {
            $varName = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
            $argConstant  = strtoupper(str_replace("-", "_", $arg));
            $methodLogic .= sprintf("\r\n\r\n\t\t\$%s = null;", $varName);
            $methodLogic .= sprintf("\r\n\t\tif (array_key_exists(static::%s, \$data)) {", $argConstant);
            $methodLogic .= sprintf("\r\n\t\t\t\$%s = \$data[static::%s];", $varName, $argConstant);
            $methodLogic .= "\r\n\t\t}";
            $args[] = "$".$varName;
        }
        $return = "new static(\r\n\t\t\t".implode(", \r\n\t\t\t", $args)."\r\n\t\t)";
        $interfaceNamespace = $this->getShortInterfaceName($this->name, DataTypeInterface::STRUCTURE_TYPE_DTO);

        return $this->renderMethod(
            static::METHOD_TEMPLATE_TYPE_STATIC,
            "Convert array to DTO object.",
            "denormalize",
            "array \$data",
            "static",
            $methodLogic,
            $return
        );
    }
}
