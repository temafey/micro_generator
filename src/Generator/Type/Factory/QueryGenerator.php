<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type\Factory;

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
    protected $allowedMethods = [];

    protected $makeQueriesInstanceByType = [];

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
        $extends = "";
        $classNamespace = $this->getClassNamespace($this->type);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $this->addUseStatement("MicroModule\Base\Domain\Query\QueryInterface as BaseQueryInterface");
        $this->addUseStatement("MicroModule\Base\Domain\Exception\FactoryException");
        $this->addUseStatement("MicroModule\Base\Domain\Dto\DtoInterface");
        //$this->addUseStatement("MicroModule\Base\Domain\ValueObject\ProcessUuid");
        //$this->addUseStatement("MicroModule\Base\Domain\ValueObject\Uuid");
        //$this->addUseStatement("MicroModule\Base\Domain\ValueObject\Id");
        $implements[] = $shortClassName."Interface";
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $name => $query) {
            $methods[] = $this->renderQueryMethod($name, $query);
        }
        $this->additionalVariables['allowedQueries'] = implode(", \r\n\t\t", $this->allowedMethods).",";
        $this->additionalVariables['makeQueriesInstanceByType'] = implode(", \r\n\t\t\t", $this->makeQueriesInstanceByType).",";

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FACTORY_QUERY,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderQueryMethod(string $queryName, array $structure): string
    {
        $shortQueryClassName = $this->getShortClassName($queryName, DataTypeInterface::STRUCTURE_TYPE_QUERY);
        $this->addUseStatement($this->getClassName($queryName, DataTypeInterface::STRUCTURE_TYPE_QUERY));
        $methodComment = sprintf("Create %s Query.", $shortQueryClassName);
        $methodArguments = [];
        $queryArguments = [];
        $additionalVariables = [];
        
        $queryConstant  = strtoupper(str_replace("-", "_", $queryName))."_QUERY";
        $methodName = sprintf("make%s", $shortQueryClassName);
        $this->allowedMethods[] = sprintf("self::%s", $queryConstant);
        $this->makeQueriesInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $queryConstant, $shortQueryClassName);

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            $this->addUseStatement($this->getValueObjectClassName($arg));
            $shortClassName = $this->getValueObjectShortClassName($arg);

            if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]) {
                throw new Exception(sprintf("Argument '%s' in ValueObjects structure not found!", $arg));
            }
            $propertyType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $propertyType." $".$propertyName;
            $queryArguments[] = sprintf("%s::fromNative($%s)", $shortClassName, $propertyName);
        }
        $additionalVariables["shortFactoryClassName"] = $shortQueryClassName;
        $additionalVariables["factoryArguments"] = implode(", \r\n\t\t\t", $queryArguments);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY,
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortQueryClassName,
            "",
            "",
            $additionalVariables
        );
    }
}
