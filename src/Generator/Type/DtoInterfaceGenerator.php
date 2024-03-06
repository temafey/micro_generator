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
class DtoInterfaceGenerator extends AbstractGenerator
{
    protected $dtoConstants = [];
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
        $this->addUseStatement("MicroModule\Common\Domain\Dto\DtoInterface");
        $interfaceNamespace = $this->getInterfaceNamespace($this->type);

        foreach ($this->structure as $arg) {
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg])) {
                throw new Exception(sprintf("Value object for dto '%s' was not found!", $arg));
            }

            if ($methodBody = $this->renderGetMethod($arg)) {
                continue;
            }
            $methods[] = $methodBody;
        }
        $this->additionalVariables['dtoConstants'] = "\r\n\t".implode("; \r\n\t", $this->dtoConstants).";";
        $extends = "DtoInterface";

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_DTO_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
        );
    }

    protected function renderGetMethod(string $arg): ?string
    {
        $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
        $methodName = "get".$shortClassName;
        $propertyComment = sprintf("%s value.", $scalarType);
        $methodComment = sprintf("Return %s value.", $scalarType);
        $argConstant  = strtoupper(str_replace("-", "_", $arg));
        $this->dtoConstants[] = sprintf("public const %s = \"%s\"", $argConstant, $arg);

        return null;

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_INTERFACE,
            $methodComment,
            $methodName,
            "",
            "?".$scalarType,
            "",
            ""
        );
    }
}
