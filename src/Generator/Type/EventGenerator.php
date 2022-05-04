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
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);        
        $this->addUseStatement("Assert\Assertion");
        $this->addUseStatement("Assert\AssertionFailedException");
        
        if ($this->useCommonComponent) {
            $this->addUseStatement("MicroModule\Common\Domain\Event\AbstractEvent");
        } else {
            $this->addUseStatement($classNamespace. "\\AbstractEvent");
        }
        $extends = "AbstractEvent";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $methodLogic = $this->renderValueObjectGetMethod($arg);

            if (null === $methodLogic) {
                continue;
            }
            $methods[] = $methodLogic;
        }
        if (!empty($this->constructArgumentsAssignment)) {
            $methodLogic = implode("", $this->constructArgumentsAssignment);
            $methodLogic .= "\r\n\t\tparent::__construct(\$processUuid, \$uuid);";
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
            $methods[] = $this->renderDeserializeMethod();
            $methods[] = $this->renderSerializeMethod();
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

    protected function renderValueObjectGetMethod(string $valueObjectName): ?string
    {
        $shortClassName = $this->getShortClassName($valueObjectName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $propertyName = lcfirst($shortClassName);
        $methodName = "get".$shortClassName;

        if ($valueObjectName === self::UNIQUE_KEY_UUID) {
            $this->addUseStatement("Ramsey\Uuid\UuidInterface");
            $shortClassName = "UuidInterface";
        } elseif ($this->useCommonComponent && $valueObjectName === self::UNIQUE_KEY_PROCESS_UUID) {
            $this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        } else {
            $this->addUseStatement(sprintf("%s;", $this->getClassName($valueObjectName, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT)));
            $this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        }
        $this->constructArguments[] = $shortClassName." $".$propertyName;

        if ($this->useCommonComponent && in_array($valueObjectName, self::UNIQUE_KEYS)) {
            return null;
        }
        $methodComment = sprintf("Return %s value object.", $shortClassName);
        $this->addProperty($propertyName, $shortClassName, $methodComment);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            $shortClassName,
            "",
            "\$this->".$propertyName
        );
    }

    protected function renderDeserializeMethod(): string
    {
        $assertion = [];
        $this->constructArguments = [];

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $assertion[] = sprintf("\r\n\t\tAssertion::keyExists(\$data, '%s');", $arg);
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $this->constructArguments[] = sprintf("\r\n\t\t\t$shortClassName::fromNative(\$data['%s'])", $arg);
        }

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            'Initialize event from data array.',
            "deserialize",
            "array \$data",
            "static",
            implode("", $assertion),
            "new static(".implode(",", $this->constructArguments)."\r\n\t\t)"
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
            "",
            "array",
            "",
            "[".implode(",", $arguments)."\r\n\t\t]"
        );
    }
}
