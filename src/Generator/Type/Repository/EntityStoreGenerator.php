<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator\Type\Repository;

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
class EntityStoreGenerator extends AbstractGenerator
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
        $entityName = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY]?:'Entity';
        $this->addUseStatement($this->getInterfaceName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $this->addUseStatement("InvalidArgumentException");
        $this->addUseStatement("MicroModule\Snapshotting\EventSourcing\SnapshottingEventSourcingRepository");
        $this->addUseStatement("MicroModule\Snapshotting\EventSourcing\SnapshottingEventSourcingRepositoryException");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $this->addUseStatement("Broadway\EventSourcing\EventSourcingRepository");
        $this->addUseStatement("Broadway\EventStore\EventStore");
        $this->addUseStatement("MicroModule\Snapshotting\Snapshot\SnapshotRepositoryInterface");
        $this->addUseStatement("MicroModule\Snapshotting\Snapshot\TriggerInterface");
        $extends = "SnapshottingEventSourcingRepository";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type, $this->name);
        $interfaceNamespace = $this->getInterfaceName($this->name, $this->type);
        $interfaceShortName = $this->getShortInterfaceName($this->name, $this->type);
        $this->addUseStatement($interfaceNamespace);
        $this->addUseStatement("Ramsey\Uuid\UuidInterface");
        $implements[] = $interfaceShortName;
        $this->additionalVariables['shortEntityInterfaceName'] = $this->getShortInterfaceName(
            $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], 
            DataTypeInterface::STRUCTURE_TYPE_ENTITY
        );
        $this->additionalVariables['entityRepositoryName'] = ucfirst($this->underscoreAndHyphenToCamelCase($entityName))."Repository";
        $this->additionalVariables['entityName'] = $this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY];

        return $this->renderClass(
            self::CLASS_TEMPLATE_REPOSITORY_ENTITY_STORE,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods
        );
    }



    /**
     * Set source file full path.
     */
    protected function setSourceFile(): void
    {
        $this->sourceFile = $this->layerPatternPath.DIRECTORY_SEPARATOR.$this->getShortClassName($this->name , $this->type).".php";
    }
}
