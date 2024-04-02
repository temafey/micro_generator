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
class EventSourcingStoreGenerator extends AbstractGenerator
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
        $this->addUseStatement($this->getClassName($entityName, DataTypeInterface::STRUCTURE_TYPE_ENTITY));
        $this->addUseStatement("Broadway\EventHandling\EventBus as EventBusInterface");
        $this->addUseStatement("Broadway\EventSourcing\AggregateFactory\PublicConstructorAggregateFactory");
        $this->addUseStatement("Broadway\EventSourcing\EventSourcingRepository");
        $this->addUseStatement("Broadway\EventSourcing\EventStreamDecorator as EventStreamDecoratorInterface");
        $this->addUseStatement("Broadway\EventStore\EventStore as EventStoreInterface");
        $this->addUseStatement("Symfony\Component\DependencyInjection\Attribute\Autowire");
        $extends = "EventSourcingRepository";
        $implements = [];
        $useTraits = [];
        $methods = [];
        $classNamespace = $this->getClassNamespace($this->type, $this->name);
        $addVar = [
          "entityName" => ucfirst($this->underscoreAndHyphenToCamelCase($entityName)),
        ];

        return $this->renderClass(
            self::CLASS_TEMPLATE_REPOSITORY_EVENT_SOURCING_STORE,
            $classNamespace,
            $this->useStatement,
            $extends,
            $implements,
            $useTraits,
            $this->properties,
            $methods,
            $addVar
        );
    }
}
