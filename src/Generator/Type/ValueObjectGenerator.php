<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type;

use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use Exception;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;

/**
 * Generator for
 *
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 *
 * @SuppressWarnings(PHPMD)
 */
class ValueObjectGenerator extends AbstractGenerator
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
     *
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function generate(): ?string
    {
        $useStatement = [];
        $implements = [];
        $useTraits = [];
        $properties = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $extends = "";

        if ($this->structure['type'] !== DataTypeInterface::VALUE_OBJECT_TYPE_ENTITY) {
            $extendsClassName = $this->getValueObjectClassName($this->structure['type']);
            $extendsShortClassName = "Base" . $this->getValueObjectShortClassName($this->structure['type']);
            $useStatement[] = "\r\nuse " . $extendsClassName . " as $extendsShortClassName;";
            $extends = $extendsShortClassName;

            return $this->renderClass(
                self::CLASS_TEMPLATE_TYPE_DEFAULT,
                $classNamespace,
                $useStatement,
                $extends,
                $implements,
                $useTraits,
                $properties,
                $methods
            );
        }

        $useStatement[] = "\r\nuse Broadway\Serializer\Serializable;";
        $useStatement[] = "\r\nuse MicroModule\ValueObject\ValueObjectInterface;";
        $implements[] = "Serializable";
        $implements[] = "ValueObjectInterface";
        $comparedFieldsProperty = "[";
        $methods[] = $this->renderFromArrayMethod();
        $methods[] = $this->renderToArrayMethod();

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $useStatement[] = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $methodComment = sprintf("Return %s value object.", $shortClassName);
            $propertyName = lcfirst($shortClassName);
            $defaultValue = "null";
            $comparedFieldsProperty .= sprintf("\r\n\t\t'%s',", $arg);
            $properties[] = $this->renderProperty(
                self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                DataTypeInterface::PROPERTY_VISIBILITY_PROTECTED,
                "?".$shortClassName,
                $propertyName,
                $defaultValue
            );
            $methods[] = $this->renderMethod(
                self::METHOD_TEMPLATE_TYPE_DEFAULT,
                $methodComment,
                $methodName,
                "",
                "?".$shortClassName,
                "",
                "\$this->".$propertyName
            );
        }
        $comparedFieldsProperty .= "\r\n\t]";
        $comparedFieldsProperty = $this->renderProperty(
            self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
            "Fields, that should be compared.",
            DataTypeInterface::PROPERTY_VISIBILITY_PUBLIC,
            DataTypeInterface::PROPERTY_CONSTANT,
            "COMPARED_FIELDS",
            $comparedFieldsProperty
        );
        array_unshift($properties, $comparedFieldsProperty);

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_VALUE_OBJECT,
            $classNamespace,
            $useStatement,
            $extends,
            $implements,
            $useTraits,
            $properties,
            $methods
        );
    }

    protected function renderFromArrayMethod(): string
    {
        $methodBody = "\r\n\t\t\$valueObject = new static();";
        $properties = [];

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            $properties[] = sprintf("\r\n\t\tif (isset(\$data['%s'])) {\r\n\t\t\t\$valueObject->%s = %s::fromNative(\$data['%s']);\r\n\t\t}", $arg, $propertyName, $shortClassName, $arg);
        }
        $methodBody .= implode("\r\n", $properties);
        $shortClassName = $this->getShortClassName($this->name, $this->type);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            sprintf("Build %s object from array.", $shortClassName),
            "fromArray",
            "array \$data",
            "static",
            $methodBody,
            "\$valueObject"
        );
    }

    protected function renderToArrayMethod(): string
    {
        $methodBody = "\r\n\t\t\$data = [];";
        $properties = [];

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyName = lcfirst($shortClassName);
            $properties[] = sprintf("\r\n\t\tif (null !== \$this->%s) {\r\n\t\t\t\$data['%s'] = \$this->%s->toNative();\r\n\t\t}", $propertyName, $arg, $propertyName);
        }
        $methodBody .= implode("\r\n", $properties);
        $shortClassName = $this->getShortClassName($this->name, $this->type);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            sprintf("Build %s object from array.", $shortClassName),
            "toArray",
            "",
            "array",
            $methodBody,
            "\$data"
        );
    }
}
