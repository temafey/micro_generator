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
        $this->addUseStatement("Symfony\Component\HttpKernel\Attribute\MapRequestPayload");
        $this->addUseStatement("Symfony\Component\Routing\Annotation\Route");
        $this->addUseStatement("Symfony\Bundle\FrameworkBundle\Controller\AbstractController");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $extends = "AbstractController";

        foreach ($this->structure as $name => $action) {
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$action[DataTypeInterface::STRUCTURE_TYPE_COMMAND]])) {
                throw new Exception(sprintf("Command '%s' was not found!", $action[DataTypeInterface::STRUCTURE_TYPE_COMMAND]));
            }
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]])) {
                throw new Exception(sprintf("Dto '%s' was not found!", $action[DataTypeInterface::STRUCTURE_TYPE_DTO]));
            }
            if (!isset($this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]])) {
                throw new Exception(sprintf("Value object for dto '%s' was not found!", $action[DataTypeInterface::STRUCTURE_TYPE_DTO]));
            }
            $methods[] = $this->renderActionMethod($name, $action);
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

    protected function renderActionMethod(string $actionName, array $action): string
    {
        $dtoParameters = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$action[DataTypeInterface::STRUCTURE_TYPE_DTO]];
        $qaParametrs = [];

        if (isset($action[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
            $returnType = DataTypeInterface::STRUCTURE_TYPE_COMMAND;
            $busType = $action[DataTypeInterface::STRUCTURE_TYPE_COMMAND];
        } elseif (isset($action[DataTypeInterface::STRUCTURE_TYPE_QUERY])) {
            $returnType = DataTypeInterface::STRUCTURE_TYPE_QUERY;
            $busType = $action[DataTypeInterface::STRUCTURE_TYPE_QUERY];
        } else {
            throw new Exception(sprintf("Command bus type in contoller '%s' was not set!", $this->name));
        }

        foreach ($dtoParameters as $arg) {
            $qaParametrs[] = $this->renderQaParameter($action, $arg);
        }
        $dtoShortClassName = $this->getShortClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO);
        $actionCommand = $this->domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$action[DataTypeInterface::STRUCTURE_TYPE_COMMAND]];
        $methodComment = sprintf("Request of \'%s\' to process \'%s\' %s", $dtoShortClassName, $this->underscoreAndHyphenToCamelCase($busType), $returnType);
        $additionalVariables = [];
        $additionalVariables["methodRoute"] = $action["route"];
        $additionalVariables["methodRouteType"] = strtoupper($action["method"]);
        $additionalVariables["methodResponseType"] = "array";
        $additionalVariables["methodResponseDesc"] = $methodComment;
        $additionalVariables["methodResponseClass"] = $dtoShortClassName;
        $additionalVariables["methodTag"] = $this->name;
        $additionalVariables["methodQaParameters"] = implode("", $qaParametrs);
        $methodLogic = $this->renderActionLogic($action);
        $return = "new JsonResponse([\"uuid\" => \$result])";
        $arguments = sprintf("#[MapRequestPayload] %s \$%s", $dtoShortClassName, lcfirst($dtoShortClassName));

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
        $additionalVariables["parametrIn"] = "query";
        $dtoShortClassName = $this->getShortClassName($dtoName, DataTypeInterface::STRUCTURE_TYPE_DTO);
        $additionalVariables["description"] = sprintf("The field \'%s\' of \'%s\'", $arg, $dtoShortClassName);
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

    protected function renderActionLogic(array $action): string
    {
        if (isset($action[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
            $returnType = DataTypeInterface::STRUCTURE_TYPE_COMMAND;
            $busType = $action[DataTypeInterface::STRUCTURE_TYPE_COMMAND];
            $this->addUseStatement($this->getInterfaceName(DataTypeInterface::STRUCTURE_TYPE_COMMAND, DataTypeInterface::STRUCTURE_TYPE_FACTORY));
            $shortClassName = $this->getShortInterfaceName(DataTypeInterface::STRUCTURE_TYPE_COMMAND, DataTypeInterface::STRUCTURE_TYPE_FACTORY);
            $commandBusContainerServicename = $this->getContainerServiceName($returnType, DataTypeInterface::STRUCTURE_TYPE_COMMAND_BUS);
            $autowiring = sprintf("#[Autowire(service: '%s')]", $commandBusContainerServicename);
            $constructArgument = sprintf("%s protected %sBus $%sBus", $autowiring, ucfirst($returnType), lcfirst($returnType));
            
            if (!in_array($constructArgument, $this->constructArguments)) {
                $this->constructArguments[] = $constructArgument;
            }
            $constructArgument = sprintf("protected %s $%sFactory", $shortClassName, lcfirst($returnType));
            
            if (!in_array($constructArgument, $this->constructArguments)) {
                $this->constructArguments[] = $constructArgument;
            }
        } elseif (isset($action[DataTypeInterface::STRUCTURE_TYPE_QUERY])) {
            $returnType = DataTypeInterface::STRUCTURE_TYPE_QUERY;
            $busType = $action[DataTypeInterface::STRUCTURE_TYPE_QUERY];
            $this->addUseStatement($this->getInterfaceName(DataTypeInterface::STRUCTURE_TYPE_QUERY, DataTypeInterface::STRUCTURE_TYPE_FACTORY));
            $shortClassName = $this->getShortInterfaceName(DataTypeInterface::STRUCTURE_TYPE_QUERY, DataTypeInterface::STRUCTURE_TYPE_FACTORY);
            $commandBusContainerServicename = $this->getContainerServiceName($returnType, DataTypeInterface::STRUCTURE_TYPE_COMMAND_BUS);
            $autowiring = sprintf("#[Autowire(service: '%s')]\n\t\t", $commandBusContainerServicename);
            $constructArgument = sprintf("%s protected %sBus $%sBus", $autowiring, ucfirst($returnType), lcfirst($returnType));
                
            if (!in_array($constructArgument, $this->constructArguments)) {
                $this->constructArguments[] = $constructArgument;
            }
            $this->constructArguments[] = sprintf("protected %s $%sFactory", $shortClassName, lcfirst($returnType));

            if (!in_array($constructArgument, $this->constructArguments)) {
                $this->constructArguments[] = $constructArgument;
            }
        } else {
            throw new Exception(sprintf("Command bus type in contoller '%s' was not set!", $this->name));
        }
        $this->addUseStatement($this->getClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO));
        $dtoShortClassName = $this->getShortClassName($action[DataTypeInterface::STRUCTURE_TYPE_DTO], DataTypeInterface::STRUCTURE_TYPE_DTO);
        $argConstant  = $this->getCommandFactoryConst($busType);

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
