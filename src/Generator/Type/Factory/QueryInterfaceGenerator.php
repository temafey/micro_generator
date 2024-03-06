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
class QueryInterfaceGenerator extends AbstractGenerator
{
    protected $queryConstants = [];

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
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, $this->type);
        //$this->addUseStatement("MicroModule\Common\Domain\ValueObject\ProcessUuid");
        //$this->addUseStatement("MicroModule\Common\Domain\ValueObject\Uuid");
        $this->addUseStatement("MicroModule\Common\Domain\Factory\QueryFactoryInterface as BaseQueryFactoryInterface");
        $extends = "BaseQueryFactoryInterface";
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $name => $query) {
            $methods[] = $this->renderQueryMethod($name, $query);
        }
        $this->additionalVariables['queryConstants'] = "\r\n\t".implode("; \r\n\t", $this->queryConstants).";";

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_QUERY_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
        );
    }

    protected function renderQueryMethod(string $queryName, array $structure): string
    {
        $shortQueryClassName = $this->getShortClassName($queryName, DataTypeInterface::STRUCTURE_TYPE_QUERY);
        $this->addUseStatement($this->getClassName($queryName, DataTypeInterface::STRUCTURE_TYPE_QUERY));
        $methodComment = sprintf("Create %s Query.", $shortQueryClassName);
        $methodArguments = [];
        $queryArguments = [];
        $queryConstant  = strtoupper(str_replace("-", "_", $queryName))."_QUERY";
        $methodName = sprintf("make%s", $shortQueryClassName);
        $this->queryConstants[] = sprintf("public const %s = \"%s\"", $queryConstant, $shortQueryClassName);
        $this->makeQueriesInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $queryConstant, $shortQueryClassName);

        foreach ($structure[DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] as $arg) {
            if (!in_array($arg, self::UNIQUE_KEYS)) {
                $this->addUseStatement($this->getClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT));
            }
            $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
            $propertyType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
            $propertyName = lcfirst($shortClassName);
            $methodArguments[] = $propertyType." $".$propertyName;
        }

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            implode(", ", $methodArguments),
            $shortQueryClassName
        );
    }
}
