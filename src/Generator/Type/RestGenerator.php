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
class RestGenerator extends AbstractGenerator
{
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
        $this->addUseStatement("League\Tactician\CommandBus");
        $this->addUseStatement("Nelmio\ApiDocBundle\Annotation\Model");
        $this->addUseStatement("OpenApi\Attributes as OA");
        $this->addUseStatement("Symfony\Component\HttpFoundation\JsonResponse");
        $this->addUseStatement("Symfony\Component\Routing\Annotation\Route");
        $this->addUseStatement("Symfony\Bundle\FrameworkBundle\Controller\AbstractController");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $extends = "AbstractController";

        foreach ($this->structure as $name => $action) {
            $actionType = false;

            if (isset($action[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
                $actionType = DataTypeInterface::STRUCTURE_TYPE_COMMAND;
                $this->addUseStatement("Symfony\Component\HttpKernel\Attribute\MapRequestPayload");
            } elseif (isset($action[DataTypeInterface::STRUCTURE_TYPE_QUERY])) {
                $actionType = DataTypeInterface::STRUCTURE_TYPE_QUERY;
                $this->addUseStatement("Symfony\Component\HttpKernel\Attribute\MapQueryString");
            }
            if (!$actionType) {
                throw new Exception(sprintf("Action type was not set for action '%s'!", $name));
            }

            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][$actionType][$action[$actionType]])) {
                throw new Exception(sprintf("%s '%s' was not found!", ucfirst($actionType), $action[$actionType]));
            }
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]])) {
                throw new Exception(sprintf("Dto '%s' was not found!", $action[DataTypeInterface::STRUCTURE_TYPE_DTO]));
            }
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]])) {
                throw new Exception(sprintf("Value object for dto '%s' was not found!", $action[DataTypeInterface::STRUCTURE_TYPE_DTO]));
            }
            $methods[] = $this->renderActionMethod($name, $actionType, $action);
        }

        if (!empty($this->constructArguments)) {
            $methodLogic = implode("", $this->constructArgumentsAssignment);
            array_unshift(
                $methods, $this->renderMethod(
                    static::METHOD_TEMPLATE_TYPE_DEFAULT,
                    "Constructor",
                    "__construct",
                "\n\t\t".implode(",\n\t\t", $this->constructArguments)."\n\t",
                    "",
                    $methodLogic,
                    ""
                )
            );
        }

        return $this->renderClass(
            static::CLASS_TEMPLATE_TYPE_FULL,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }

    protected function renderActionMethod(string $actionName, string $actionType, array $action): string
    {
        $dtoParameters = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]];
        $qaParametrs = [];

        if (isset($action[$actionType])) {
            $returnType = $actionType;
            $busType = $action[$actionType];
        }

        foreach ($dtoParameters as $arg) {
            $qaParametrs[] = $this->renderQaParameter($action, $arg);
        }
        $dtoShortClassName = $this->getShortClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO);
        $actionCommand = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][$actionType][$action[$actionType]];
        $methodComment = sprintf("Request of \'%s\' to process \'%s\' %s", $dtoShortClassName, $this->underscoreAndHyphenToCamelCase($busType), $returnType);
        $additionalVariables = [];
        $additionalVariables["methodRoute"] = $action["route"];
        $additionalVariables["methodRouteType"] = strtoupper($action["method"]);
        $additionalVariables["methodResponseType"] = "array";
        $additionalVariables["methodResponseDesc"] = $methodComment;
        $additionalVariables["methodResponseClass"] = $dtoShortClassName;
        $additionalVariables["methodTag"] = $this->name;
        $additionalVariables["methodQaParameters"] = implode("", $qaParametrs);
        $methodLogic = $this->renderActionLogic($actionType, $action);
        
        if ($actionType === DataTypeInterface::STRUCTURE_TYPE_COMMAND) {
            $return = "new JsonResponse([\"uuid\" => \$result])";
            $arguments = sprintf("#[MapRequestPayload] %s \$%s", $dtoShortClassName, lcfirst($dtoShortClassName));
        } elseif ($actionType === DataTypeInterface::STRUCTURE_TYPE_QUERY) {
            $return = "new JsonResponse(\$result)";
            $arguments = sprintf("#[MapQueryString] %s \$%s", $dtoShortClassName, lcfirst($dtoShortClassName));
        }

        return $this->renderMethod(
            static::METHOD_TEMPLATE_TYPE_CONTROLLER_ACTION,
            str_replace("\\", "", $methodComment),
            lcfirst($this->underscoreAndHyphenToCamelCase($actionName))."Action",
            $arguments,
            "JsonResponse",
            $methodLogic,
            $return,
            $additionalVariables
        );
    }
    
    protected function renderQaParameter(array $action, string $arg): string
    {
        $dtoName = $action[DataTypeInterface::STRUCTURE_TYPE_DTO];
        $property = lcfirst($this->underscoreAndHyphenToCamelCase($arg));
        $valueObjectType = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$arg]["type"];
        $propertyType = $this->getValueObjectScalarType($valueObjectType);
        $additionalVariables = [];
        $additionalVariables["name"] = $arg;
        $dtoShortClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $additionalVariables["description"] = sprintf("The field \'%s\' of \'%s\'", $arg, $dtoShortClassName);
        $additionalVariables["parametrIn"] = "query";
        $additionalVariables["type"] = $propertyType;

        return $this->renderMethod(
            static::METHOD_TEMPLATE_TYPE_CONTROLLER_QA_PARAMETR,
            "",
            "",
            "",
            "",
            "",
            "",
            $additionalVariables
        );
    }

    protected function renderActionLogic(string $actionType, array $action): string
    {
        $returnType = $actionType;
        $busType = $action[$actionType];
        $this->addUseStatement($this->getInterfaceName($actionType, DataTypeInterface::STRUCTURE_TYPE_FACTORY));
        $shortClassName = $this->getShortInterfaceName($actionType, DataTypeInterface::STRUCTURE_TYPE_FACTORY);
        $commandBusContainerServicename = $this->getContainerServiceName($returnType, DataTypeInterface::STRUCTURE_TYPE_COMMAND_BUS);
        $autowiring = sprintf("#[Autowire(service: '%s')]", $commandBusContainerServicename);
        $constructArgument = sprintf("%s protected commandBus $%sBus", $autowiring, lcfirst($returnType));
        
        if (!in_array($constructArgument, $this->constructArguments)) {
            $this->constructArguments[] = $constructArgument;
        }
        $constructArgument = sprintf("protected %s $%sFactory", $shortClassName, lcfirst($returnType));
        
        if (!in_array($constructArgument, $this->constructArguments)) {
            $this->constructArguments[] = $constructArgument;
        }
        $this->addUseStatement($this->getClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO));
        $dtoShortClassName = $this->getShortClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO);
        $argConstant  = ($actionType === DataTypeInterface::STRUCTURE_TYPE_COMMAND) ? $this->getCommandFactoryConst($busType) : $this->getQueryFactoryConst($busType);

        return sprintf(
            "\n\t\t\$result = \$this->%sBus->handle(
            \$this->%sFactory->make%sInstanceByTypeFromDto(
                %sFactoryInterface::%s,
                \$%s
            )
        );",
            $returnType,
            $returnType,
            ucfirst($returnType),
            ucfirst($returnType),
            $argConstant,
            lcfirst($dtoShortClassName)
        );
    }

}
