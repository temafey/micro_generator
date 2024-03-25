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
class EntityStoreInterfaceGenerator extends AbstractGenerator
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
        //$this->addUseStatement("InvalidArgumentException");
        //$this->addUseStatement("MicroModule\Snapshotting\EventSourcing\SnapshottingEventSourcingRepository");
        $this->addUseStatement("MicroModule\Snapshotting\EventSourcing\SnapshottingEventSourcingRepositoryException");
        $implements = [];
        $methods = [];
        $interfaceNamespace = $this->getClassNamespace($this->type, $this->name);
        $this->addUseStatement("Ramsey\Uuid\UuidInterface");
        $this->additionalVariables['shortEntityInterfaceName'] = $this->getShortInterfaceName($this->structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY], DataTypeInterface::STRUCTURE_TYPE_ENTITY);

        return $this->renderInterface(
            self::CLASS_TEMPLATE_REPOSITORY_ENTITY_STORE_INTERFACE,
            $interfaceNamespace,
            $this->useStatement,
            $methods
        );
    }
}
