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
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type);
        
        if ($this->useCommonComponent) {
            $this->addUseStatement("MicroModule\Common\Domain\Query\AbstractQuery");
        } else {
            $this->addUseStatement($classNamespace. "\\"."AbstractQuery");
        }
        $extends = "AbstractQuery";

        foreach ($this->structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) { 
            $renderedMethod = $this->renderStructureMethod($arg);

            if (null === $renderedMethod) {
                continue;
            }
            $methods[] = $renderedMethod;
        }

        if (!empty($this->constructArguments)) {
            //$methodLogic = implode("", $this->constructArgumentsAssignment);
            $methodLogic = "\r\n\t\tparent::__construct(\$processUuid);";
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
        $className = $this->getValueObjectClassName($arg);
        $this->addUseStatement($className);
        $shortClassName = $this->getValueObjectShortClassName($arg);
        $propertyName = lcfirst($shortClassName);
        $methodComment = sprintf("Return %s value object.", $shortClassName);

        if ($this->useCommonComponent && $arg === static::KEY_UNIQUE_PROCESS_UUID) {
            $this->constructArguments[] = sprintf("\n\t\t%s $%s", $shortClassName, $propertyName);
            return null;
        } else {
            $this->constructArguments[] = sprintf("\n\t\tprotected %s $%s", $shortClassName, $propertyName);
        }
        ///$this->constructArgumentsAssignment[] = sprintf("\r\n\t\t\$this->%s = $%s;", $propertyName, $propertyName);
        //$this->addProperty($propertyName, $shortClassName, $methodComment);
        $methodName = "get".$shortClassName;

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "",
            $shortClassName,
            "",
            "\$this->".$propertyName,
            $addVar
        );
    }
}
