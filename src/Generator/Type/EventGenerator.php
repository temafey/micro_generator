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
            $this->addUseStatement("MicroModule\Base\Domain\Event\AbstractEvent");
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

        if (!empty($this->constructArguments)) {
            $this->constructArguments[] = "?Payload \$payload = null";
            $this->addUseStatement("MicroModule\Base\Domain\ValueObject\Payload");
            $methodLogic = "\r\n\t\tparent::__construct(\$processUuid, \$uuid, \$payload);";
                array_unshift(
                    $methods, $this->renderMethod(
                    self::METHOD_TEMPLATE_TYPE_DEFAULT,
                    "Constructor",
                    "__construct",
                    "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
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
        $className = $this->getValueObjectClassName($valueObjectName);
        $this->addUseStatement($className);
        $shortClassName = $this->getValueObjectShortClassName($valueObjectName);
        $propertyName = lcfirst($shortClassName);
        $methodName = "get".$shortClassName;
        $this->constructArguments[] = (in_array($valueObjectName, self::UNIQUE_KEYS)
            ? $shortClassName." $".$propertyName
            : "protected ".$shortClassName." $".$propertyName);

        if (
            $this->useCommonComponent &&
            $valueObjectName !== self::KEY_UNIQUE_ID &&
            in_array($valueObjectName, self::UNIQUE_KEYS)
        ) {
            return null;
        }
        $methodComment = sprintf("Return %s value object.", $shortClassName);

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
        $methodLogic = implode("", $assertion);
        $methodLogic .= "\r\n\t\t\$event = new static(".implode(",", $this->constructArguments)."\r\n\t\t);";
        $methodLogic .= "\r\n\r\n\t\tif (isset(\$data['payload'])) {";
        $methodLogic .= "\r\n\t\t\t\$event->setPayload(Payload::fromNative(\$data['payload']));";
        $methodLogic .= "\r\n\t\t}";

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_STATIC,
            'Initialize event from data array.',
            "deserialize",
            "array \$data",
            "static",
            $methodLogic,
            "\$event"
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
        $methodLogic = "\r\n\t\t\$data = [".implode(",", $arguments)."\r\n\t\t];";
        $methodLogic .= "\r\n\r\n\t\tif (\$this->payload !== null) {";
        $methodLogic .= "\r\n\t\t\t\$data['payload'] = \$this->payload->toNative();";
        $methodLogic .= "\r\n\t\t}";

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            'Convert event object to array.',
            "serialize",
            "",
            "array",
            $methodLogic,
            "\$data"
        );
    }
}
