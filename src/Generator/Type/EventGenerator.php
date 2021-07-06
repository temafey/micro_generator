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
class EventGenerator extends AbstractGenerator
{
    /**
     * Is throw exception if return type was not found.
     *
     * @var bool
     */
    protected $returnTypeNotFoundThrowable = false;

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
        if (!isset($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception(sprintf("Entity for event '%s' was not found!", $this->name));
        }
        $useStatement = [];
        $implements = [];
        $useTraits = [];
        $properties = [];
        $constructArguments = [];
        $constructArgumentsInitialize = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $useStatement[] = "\r\nuse ".$classNamespace. "\\AbstractEvent;";
        $useStatement[] = "\r\nuse Assert\Assertion;";
        $useStatement[] = "\r\nuse Assert\AssertionFailedException;";
        $extends = "AbstractEvent";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $useStatement[] = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $methodComment = sprintf("Return %s value object.", $shortClassName);
            $propertyName = lcfirst($shortClassName);
            $properties[] = $this->renderProperty(
                self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                DataTypeInterface::PROPERTY_VISIBILITY_PROTECTED,
                $shortClassName,
                $propertyName
            );
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                $methodName,
                "",
                $shortClassName,
                "",
                "\$this->".$propertyName
            );
            $constructArguments[] = $shortClassName." $".$propertyName;
            $constructArgumentsInitialize[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }
        array_unshift($methods, $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            "Constructor",
            "__construct",
            implode(", ", $constructArguments),
            "",
            implode("", $constructArgumentsInitialize),
            ""
        ));
        $methods[] = $this->renderDeserializeMethod();
        $methods[] = $this->renderSerializeMethod();

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $useStatement,
            $extends,
            $implements,
            $useTraits,
            $properties,
            $methods
        );
    }

    protected function renderDeserializeMethod(): string
    {
        $assertion = [];
        $constructArguments = [];

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $assertion[] = sprintf("\r\n\t\tAssertion::keyExists(\$data, '%s');", $arg);
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $constructArguments[] = sprintf("\r\n\t\t\t$shortClassName::fromNative(\$data['%s'])", $arg);
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            'Initialize event from data array.',
            "deserialize",
            "array \$data",
            "static",
            implode("", $assertion),
            "new static(".implode(",", $constructArguments)."\r\n\t\t)"
        );
    }

    protected function renderSerializeMethod(): string
    {
        $arguments = [];

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $arguments[] = sprintf("\r\n\t\t\t'%s' => \$this->%s()->toNative()", $arg, $methodName);
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            'Convert event object to array.',
            "serialize",
            "array \$data",
            "array",
            "",
            "[".implode(",", $arguments)."\r\n\t\t]"
        );
    }
}
