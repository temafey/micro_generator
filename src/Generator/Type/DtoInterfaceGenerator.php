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
    protected array $dtoConstants = [];

    /**
     * Render getter methods in dto.
     */
    protected bool $renderGetters = false;

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
        $argConstant  = strtoupper(str_replace("-", "_", $arg));
        $this->dtoConstants[] = sprintf("public const %s = \"%s\"", $argConstant, $arg);

        if (!$this->renderGetters) {
            return null;
        }
        $shortClassName = $this->getShortClassName($arg, DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT);
        $scalarType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
        $methodName = "get".$shortClassName;
        $methodComment = sprintf("Return %s value.", $scalarType);

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
