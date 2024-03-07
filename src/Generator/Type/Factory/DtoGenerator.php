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
class DtoGenerator extends AbstractGenerator
{
    protected array $makeDtosInstanceByType = [];

    protected array $allowedDtos = [];

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
        $this->addUseStatement("MicroModule\Common\Domain\Dto\DtoInterface");
        $this->addUseStatement("MicroModule\Base\Domain\Exception\FactoryException");
        $classNamespace = $this->getClassNamespace(DataTypeInterface::STRUCTURE_TYPE_DTO_FACTORY_INTERFACE);
        $shortClassName = $this->getShortClassName($this->name, $this->type);
        $implements[] = $shortClassName."Interface";
        $this->additionalVariables["propertyValueObjectName"] = lcfirst($this->additionalVariables["shortValueObjectName"]);

        foreach ($this->structure as $name => $dto) {
            $this->addUseStatement($this->getClassName($name, DataTypeInterface::STRUCTURE_TYPE_DTO));
            $methods[] = $this->renderCreateNewDtoMethod($name, $dto);
            $methods[] = $this->renderCreateDtoFromDataMethod($name, $dto);
        }
        $this->additionalVariables["allowedDtos"] = implode(", \r\n\t\t", $this->allowedDtos).",";
        $this->additionalVariables["makeDtosInstanceByType"] = implode(", \r\n\t\t\t", $this->makeDtosInstanceByType).",";

        return $this->renderClass(
            self::CLASS_TEMPLATE_TYPE_FACTORY_DTO,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderCreateNewDtoMethod(string $dtoName, array $structure): string
    {
        $shortDtoClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $methodComment = sprintf("Create %s object .", $shortDtoClassName);
        $methodArguments = [];
        $dtoArguments = [];
        $additionalVariables = [];
        $dtoConstant  = strtoupper(str_replace("-", "_", $dtoName))."_DTO";
        $methodName = sprintf("make%s", $shortDtoClassName);
        $this->allowedDtos[] = sprintf("self::%s", $dtoConstant);
        $this->makeDtosInstanceByType[] = sprintf("self::%s => \$this->make%s(...\$args)", $dtoConstant, $shortDtoClassName);

        foreach ($structure as $arg) {
            if (!$this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]) {
                throw new Exception(sprintf("Argument '%s' in ValueObjects structure not found!", $arg));
            }
            $propertyType = $this->getValueObjectScalarType($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]["type"]);
            $propertyName = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
            $methodArguments[] = $propertyType." $".$propertyName;
            $dtoArguments[] = sprintf("$%s", $propertyName);
        }
        $additionalVariables["shortFactoryClassName"] = $shortDtoClassName;
        $additionalVariables["factoryArguments"] = implode(", ", $dtoArguments);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_FACTORY,
            $methodComment,
            $methodName,
            "\r\n\t\t\t".implode(", \r\n\t\t\t", $methodArguments)."\r\n\t\t",
            $shortDtoClassName,
            "",
            "",
            $additionalVariables
        );
    }

    protected function renderCreateDtoFromDataMethod(string $dtoName, array $structure): string
    {
        $shortDtoClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $methodComment = sprintf("Create %s Dto.", $shortDtoClassName);
        $additionalVariables = [];
        $methodName = sprintf("make%sFromData", $shortDtoClassName);

        return $this->renderMethod(
            self::METHOD_TEMPLATE_TYPE_DEFAULT,
            $methodComment,
            $methodName,
            "array \$data",
            $shortDtoClassName,
            "",
            sprintf("%s::denormalize(\$data)", $shortDtoClassName),
            $additionalVariables
        );
    }
}
