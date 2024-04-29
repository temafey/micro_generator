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
class DtoInterfaceGenerator extends AbstractGenerator
{
    protected $dtoConstants = [];

    protected $makeDtosInstanceByType = [];

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
        $this->addUseStatement("MicroModule\Base\Application\Factory\DtoFactoryInterface as BaseDtoFactoryInterface");
        $interfaceNamespace = $this->getInterfaceNamespace(DataTypeInterface::STRUCTURE_TYPE_DTO_FACTORY_INTERFACE);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, DataTypeInterface::STRUCTURE_TYPE_DTO_FACTORY_INTERFACE);
        $extends = "BaseDtoFactoryInterface";
        $this->additionalVariables['propertyValueObjectName'] = lcfirst($this->additionalVariables['shortValueObjectName']);

        foreach ($this->structure as $name => $dto) {
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_DTO));
            $methods[] = $this->renderCreateNewDtoMethod($name, $dto);
            $methods[] = $this->renderCreateDtoFromDataMethod($name, $dto);
        }
        $this->additionalVariables['dtoConstants'] = "\r\n\t".implode("; \r\n\t", $this->dtoConstants).";";

        return $this->renderInterface(
            self::CLASS_TEMPLATE_TYPE_FACTORY_DTO_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods,
            $extends
        );
    }

    protected function renderCreateNewDtoMethod(string $dtoName, array $structure): string
    {
        $shortDtoClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $shortDtoInterfaceName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $methodComment = sprintf("Create %s Dto.", $shortDtoClassName);
        $methodArguments = [];
        $dtoArguments = [];
        
        $dtoConstant  = strtoupper(str_replace("-", "_", $dtoName))."_DTO";
        $methodName = sprintf("make%s", $shortDtoClassName);
        $this->dtoConstants[] = sprintf("public const %s = \"%s\"", $dtoConstant, $shortDtoClassName);
        $this->makeDtosInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $dtoConstant, $shortDtoClassName);

        foreach ($structure as $arg) {
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg])) {
                throw new Exception(sprintf("Value object for dto '%s' was not found!", $arg));
            }
            $propertyType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]['type']);
            $propertyName = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
            $methodArguments[] = $propertyType." $".$propertyName;
        }
        

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            "\n\t\t".implode(",\n\t\t", $methodArguments)."\n\t",
            $shortDtoInterfaceName
        );
    }

    protected function renderCreateDtoFromDataMethod(string $dtoName, array $structure): string
    {
        $shortDtoClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $shortDtoInterfaceName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $methodComment = sprintf("Create %s Dto.", $shortDtoClassName);
        $additionalVariables = [];
        $methodName = sprintf("make%sFromData", $shortDtoClassName);
        $additionalVariables["shortFactoryClassName"] = $shortDtoClassName;
        $additionalVariables["factoryArguments"] = "\$data";

        return $this->renderMethodInterface(
            $methodComment,
            $methodName,
            "array \$data",
            $shortDtoInterfaceName
        );
    }
}
