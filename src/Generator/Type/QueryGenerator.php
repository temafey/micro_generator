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
class QueryGenerator extends AbstractGenerator
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
            throw new Exception(sprintf("Entity for query '%s' was not found!", $this->name));
        }
        $useStatement = [];
        $implements = [];
        $useTraits = [];
        $properties = [];
        $constructArguments = [];
        $constructArgumentsInitialize = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        $useStatement[] = "\r\nuse ".$classNamespace. "\\"."AbstractQuery;";
        $extends = "AbstractQuery";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $useStatement[] = sprintf("\r\nuse %s;", $this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $methodName = "get".$shortClassName;
            $methodComment = "";
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
}
