<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

use Exception;
use MicroModule\Base\Infrastructure\Service\Utils\DeclarationLocator;
use MicroModule\MicroserviceGenerator\Generator\AbstractGenerator;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\CommandNotFoundException;
use MicroModule\MicroserviceGenerator\Generator\GeneratorInterface;
use MicroModule\MicroserviceGenerator\Generator\Helper\CodeHelper;

/**
 * Class ProjectBuilder.
 *
 * @SuppressWarnings(PHPMD)
 */
class ProjectBuilder implements ProjectBuilderInterface
{
    use CodeHelper;
    
    /**
     * Source path.
     */
    protected string $sourcePath;

    /**
     * Project namespace.
     */
    protected string $namespace;

    /**
     * Folders, that should be exclude from analyze.
     *
     * @var mixed[]
     */
    protected array $structure;

    /**
     * TestProject constructor.
     *
     * @param string  $sourcePath
     * @param mixed[] $structure
     */
    public function __construct(string $sourcePath, string $namespace, array $structure)
    {
        $this->sourcePath = $sourcePath;
        $this->namespace = $namespace;
        $this->structure = $structure;
    }

    /**
     * Generate tests.
     *
     * @throws Exception
     */
    public function generate(): void
    {
        if (!file_exists($this->sourcePath)) {
            throw new Exception("Source file '{$this->sourcePath}' doesn't exists!");
        }

        foreach ($this->structure as $name => $domainStructure) {
            $domainName = ucfirst($this->underscoreAndHyphenToCamelCase($name));
            $this->generateDomain($domainName, $domainStructure);
        }
    }

    /**
     * Generate domain code.
     *
     * @param string $domainName
     * @param mixed[] $domainStructure
     *
     * @throws \Exception
     */
    protected function generateDomain(string $domainName, array $domainStructure): void
    {
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
            throw new Exception('no entity section');
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT])) {
            throw new Exception('no value-object section');
        }
//        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
//            throw new Exception('no commands section');
//        }
//        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
//            throw new Exception('no repositories section');
//        }
//        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER])) {
//            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER] = [];
//        }
//        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER])) {
//            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER] = [];
//        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_SAGA])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_SAGA] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_DTO])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_DTO] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_SERVICE])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_SERVICE] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_EXCEPTION])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_EXCEPTION] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_RPC])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_RPC] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_CLI])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_CLI] = [];
        }
        $domainRootPath = $this->sourcePath.DIRECTORY_SEPARATOR.$domainName;

        if (!file_exists($domainRootPath)) {
            //Create domain main directory
            if (!mkdir($domainRootPath, 0755) && !is_dir($domainRootPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $domainRootPath));
            }
        }
        $domainStructure = $this->buildStructure($domainStructure);
        $this->generateStructure($domainName, $domainStructure, $domainRootPath);
    }

    protected function buildStructure(array $structure): array
    {
        $domainStructure = DataTypeInterface::DOMAIN_BASE_STRUCTURE;
        $structure = $this->buildDefaultStructure($structure);
        $domainStructure = $this->buildDomainStructure($structure, $domainStructure);
        $domainStructure = $this->buildApplicationStructure($structure, $domainStructure);
        $domainStructure = $this->buildInfrastructureStructure($structure, $domainStructure);
        $domainStructure = $this->buildPresentationStructure($structure, $domainStructure);

        return $domainStructure;
    }

    /**
     * Analyze and build full DDD structure for all patterns.
     *
     * @param mixed[] $structure
     *
     * @return mixed[]
     */
    protected function buildDefaultStructure(array $structure): array
    {
        $structure = $this->buildCommandStructure($structure);
        $structure = $this->buildQueryStructure($structure);

        if (!$structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR]) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR] = $this->buildProjectorStructure($structure);
        }
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY] = [];
        }
        if (empty($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE] = $this->buildEventStoreRepositoryStructure($structure);
        }
        if (empty($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_EVENT_SOURCIHNG_STORE])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_EVENT_SOURCIHNG_STORE] = $this->buildEventSourcingStoreRepositoryStructure($structure);
        }
        if (empty($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_QUERY])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_QUERY] = $this->buildQueryRepositoryStructure($structure);
        }
        if (empty($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] = $this->buildReadModelRepositoryStructure($structure);
        }

        return $structure;
    }

    /**
     * Analyze and build full DDD structure for all patterns.
     *
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildDomainStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND] as $name => $command) {
            if (!isset($command[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
                throw new Exception(sprintf("Entity for command '%s' was not found!", $name));
            }
            $entity = $command[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$name] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK][$name] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK][$entity][$name] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_TASK][$entity][$name] = $command;
            $command['name'] = $name;
            $command['type'] = DataTypeInterface::STRUCTURE_TYPE_COMMAND;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_COMMAND][] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_COMMAND][] = $command;
            $command['type'] = DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_COMMAND][] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_COMMAND][] = $command;

            foreach ($command[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $eventName => $event) {
                $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$eventName] = [
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                    'args' => $event,
                ];
                $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_EVENT][$eventName] = $event;
                $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_EVENT][$eventName] = $event;
            }
        }
        unset($domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_QUERY]);
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY] as $name => $query) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$name] = $query;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_QUERY][$name] = $query;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_QUERY][$name] = $query;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] as $name => $entity) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_ENTITY][$name] = $entity;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_ENTITY_INTERFACE][$name] = $entity;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_ENTITY][$name] = $entity;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_ENTITY][$name] = $entity;
        }
        unset($domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL]);
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] as $name => $readModel) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL][$name] = $readModel;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE][$name] = $readModel;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL][$name] = $readModel;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL][$name] = $readModel;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT] as $name => $valueObject) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$name] = $valueObject;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$name] = $valueObject;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$name] = $valueObject;
        }
        
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_DTO] as $name => $dto) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_DTO_INTERFACE][$name] = $dto;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY] as $name => $repository) {
            if (
                is_array($repository) &&
                array_key_exists(DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE, $repository) &&
                $repository[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE] === false
            ) {
                continue;
            }
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE][$name] = $repository;
        }
        //$domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_QUERY] = $this->buildQueryRepositoryStructure($structure);
        //$domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] = $this->buildReadModelRepositoryStructure($structure);

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_SERVICE] as $name => $service) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_SERVICE][$name] = $service;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_SERVICE_INTERFACE][$name] = $service;
        }

        return $domainStructure;
    }

    /**
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildApplicationStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER] as $name => $handler) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER][$name] = $handler;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER] as $name => $handler) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$name] = $handler;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER] as $name => $handler) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK][$name] = $handler;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_SAGA] as $name => $event) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_SAGA][$name] = $event;
        }

        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR] = $this->buildProjectorStructure($structure);
        }
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR] as $name => $projector) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_PROJECTOR][$name] = $projector;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_DTO] as $name => $dto) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$name] = $dto;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_DTO][$name] = $dto;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE][DataTypeInterface::STRUCTURE_TYPE_DTO][$name] = $dto;
        }

        return $domainStructure;
    }

    /**
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildInfrastructureStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY] as $name => $repository) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$name] = $repository;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_SERVICE] as $name => $service) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE][DataTypeInterface::STRUCTURE_TYPE_SERVICE][$name] = $service;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS] as $name => $migration) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE][DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS][$name] = $migration;
        }

        return $domainStructure;
    }

    protected function buildCommandStructure(array $structure): array
    {
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND] = [];
        }
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER] = [];
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] as $entity => $entityStructure) {
            foreach ($entityStructure as $commandName) {
                if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName])) {
                    $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName] = [
                        DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                    ];
                    $addCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_ADD_PREFIX);
                    $createCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_CREATE_PREFIX);
                    $deleteCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_DELETE_PREFIX);
                    $removeCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_REMOVE_PREFIX);

                    switch ($commandName) {
                        case $addCommandName:
                        case $createCommandName:
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] = [
                                GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                $entity,
                            ];
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::STRUCTURE_TYPE_EVENT] = [
                                $commandName => [
                                    GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                    GeneratorInterface::KEY_UNIQUE_UUID,
                                    $entity,
                                ]
                            ];
                            break;
                        case $deleteCommandName:
                        case $removeCommandName:
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] = [
                                GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                GeneratorInterface::KEY_UNIQUE_UUID,
                            ];
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::STRUCTURE_TYPE_EVENT] = [
                                $commandName => [
                                    GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                    GeneratorInterface::KEY_UNIQUE_UUID,
                                ]
                            ];
                            break;
                        default:
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] = [
                                GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                GeneratorInterface::KEY_UNIQUE_UUID,
                                $entity,
                            ];
                            $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName][DataTypeInterface::STRUCTURE_TYPE_EVENT] = [
                                $commandName => [
                                    GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                                    GeneratorInterface::KEY_UNIQUE_UUID,
                                    $entity,
                                ]
                            ];
                            break;
                    }
                }
                if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER][$commandName])) {
                    $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER][$commandName] = [
                        DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                    ];
                    $addCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_ADD_PREFIX);
                    $createCommandName = sprintf("%s-%s", $entity, GeneratorInterface::CREATE_METHOD_CREATE_PREFIX);

                    if ($commandName === $addCommandName || $commandName === $createCommandName) {
                        $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER][$commandName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] = [
                            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE,
                            DataTypeInterface::STRUCTURE_TYPE_FACTORY => DataTypeInterface::STRUCTURE_TYPE_ENTITY,
                        ];
                    } else {
                        $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER][$commandName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS] = [
                            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE,
                        ];
                    }
                }
            }
        }

        return $structure;
    }

    protected function buildQueryStructure(array $structure): array
    {
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY] = [];
        }
        if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER])) {
            $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER] = [];
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] as $readModel => $readModelStructure) {
            $entity = $readModelStructure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $readModel;
            $fetchOneQueryName = sprintf("%s-%s", AbstractGenerator::METHOD_TYPE_FETCH_ONE_U, $readModel);

            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$fetchOneQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$fetchOneQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                        GeneratorInterface::KEY_UNIQUE_UUID,
                    ],
                ];
            }
            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$fetchOneQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$fetchOneQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => DataTypeInterface::STRUCTURE_TYPE_QUERY,
                    ],
                    DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => $readModel,
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                ];
            }
            if (
                isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel]) &&
                isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS]) &&
                !isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$fetchOneQueryName])
            ) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$fetchOneQueryName] = $fetchOneQueryName;
            }
            $findByCriteriaQueryName = sprintf("%s-%s", AbstractGenerator::METHOD_TYPE_FIND_BY_CRITERIA_U, $readModel);

            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$findByCriteriaQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$findByCriteriaQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                        GeneratorInterface::KEY_FIND_CRITERIA,
                    ],
                ];
            }
            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$findByCriteriaQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$findByCriteriaQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => DataTypeInterface::STRUCTURE_TYPE_QUERY,
                    ],
                    DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => $readModel,
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_ARRAY,
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                ];
            }
            $findOneByQueryName = sprintf("%s-%s", AbstractGenerator::METHOD_TYPE_FIND_ONE_BY_U, $readModel);

            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$findOneByQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY][$findOneByQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        GeneratorInterface::KEY_UNIQUE_PROCESS_UUID,
                        GeneratorInterface::KEY_FIND_CRITERIA,
                    ],
                ];
            }
            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$findOneByQueryName])) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER][$findOneByQueryName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => DataTypeInterface::STRUCTURE_TYPE_QUERY,
                    ],
                    DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => $readModel,
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                ];
            }
            if (
                isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel]) &&
                isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS]) &&
                !isset($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$findByCriteriaQueryName])
            ) {
                $structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$findByCriteriaQueryName] = $findByCriteriaQueryName;
            }
        }

        return $structure;
    }

    protected function buildQueryRepositoryStructure(array $structure): array
    {
        $queryRepositoryStructure = [];

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER] as $name => $queryHandler) {
            $readModel = $queryHandler[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL];
            $entityName = $queryHandler[DataTypeInterface::STRUCTURE_TYPE_ENTITY];

            if (!isset($queryRepositoryStructure[$readModel])) {
                $queryRepositoryStructure[$readModel] = [
                    DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => $readModel,
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entityName,
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        //$this->namespace."\Domain\Repository\ReadModel\\".ucfirst($this->camelCaseToUnderscore($entity))."RepositoryInterface",
                        "MicroModule\Base\Domain\Repository\ReadModelStoreInterface",
                        $this->namespace."\Domain\Factory\ReadModelFactoryInterface",
                        $this->namespace."\Domain\Factory\ValueObjectFactoryInterface",
                    ],
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS => [],
                ];
            }
            $queryRepositoryStructure[$readModel][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$name] = $name;
        }

        return $queryRepositoryStructure;
    }

    protected function buildReadModelRepositoryStructure(array $structure): array
    {
        $readModelRepositoryStructure = [];

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] as $readModelName => $readModelStructure) {
            $entityName = $readModelStructure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $readModelName;

            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY][$entityName])) {
                throw new EntityNotFoundException(sprintf("Entity '%s' from read model '%s' not found!", $entityName, $readModelName));
            }

            foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY][$entityName] as $commandName) {
                if (!isset($readModelRepositoryStructure[$readModelName])) {
                    $readModelRepositoryStructure[$readModelName] = [
                        DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => $readModelName,
                        DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entityName,
                        DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                            "MicroModule\Base\Domain\Repository\ReadModelStoreInterface",
                            $this->namespace."\Domain\Factory\ReadModelFactoryInterface",
                            $this->namespace."\Domain\Factory\ValueObjectFactoryInterface",
                        ],
                        DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS => [],
                    ];
                }
                $readModelRepositoryStructure[$readModelName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS][$commandName] = [
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                        $readModelName => DataTypeInterface::STRUCTURE_TYPE_READ_MODEL,
                    ],
                    DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_VOID,
                ];
            }
            if (isset($readModelRepositoryStructure[$readModelName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS]["get"])) {
                continue;
            }
            $readModelRepositoryStructure[$readModelName][DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS]["get"] = [
                DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                    DataTypeInterface::DATA_TYPE_UUID => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                ],
                DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::STRUCTURE_TYPE_READ_MODEL,
            ];
        }

        return $readModelRepositoryStructure;
    }

    protected function buildProjectorStructure(array $structure): array
    {
        $projectorStructure = [];

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_READ_MODEL] as $readModelName => $readModelStructure) {
            $entityName = $readModelStructure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] ?? $readModelName;
            
            if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY][$entityName])) {
                throw new EntityNotFoundException(sprintf("Entity '%s' from read model '%s' not found!", $entityName, $readModelName));
            }
            
            foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY][$entityName] as $commandName) {
                if (!isset($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY][$entityName])) {
                    throw new CommandNotFoundException(sprintf("Command '%s' from entity '%s' not found!", $commandName, $entityName));
                }
                $command = $structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND][$commandName];

                if (!isset($projectorStructure[$readModelName])) {
                    $projectorStructure[$readModelName] = [
                        DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                            $entityName => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE,
                            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_READ_MODEL,
                            DataTypeInterface::STRUCTURE_TYPE_FACTORY_READ_MODEL,
                            //DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_QUERY_STORE,
                            //DataTypeInterface::STRUCTURE_TYPE_COMMAND_BUS,
                            //DataTypeInterface::STRUCTURE_TYPE_FACTORY_COMMAND,
                        ],
                        DataTypeInterface::STRUCTURE_TYPE_EVENT => [],
                    ];
                }

                foreach ($command[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $eventName => $event) {
                    $projectorStructure[$readModelName][DataTypeInterface::STRUCTURE_TYPE_EVENT][] = $eventName;
                }
            }
        }

        return $projectorStructure;
    }

    protected function buildEventStoreRepositoryStructure(array $structure): array
    {
        $eventStoreStructure = [];
        
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] as $name => $entity) {
            $eventStoreStructure[$name] = [
                DataTypeInterface::STRUCTURE_TYPE_ENTITY => $name,
                DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                    "MicroModule\Base\Domain\Repository\ReadModelStoreInterface",
                ],
            ];
        }

        return $eventStoreStructure;
    }

    protected function buildEventSourcingStoreRepositoryStructure(array $structure): array
    {
        $eventSourcingStoreStructure = [];

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] as $name => $entity) {
            $eventSourcingStoreStructure[$name] = [
                DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE => false,
                DataTypeInterface::STRUCTURE_TYPE_ENTITY => $name,
                DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                    "Broadway\EventStore\EventStore",
                    "Broadway\EventHandling\EventBus",
                    "eventStreamDecorators" => DataTypeInterface::DATA_TYPE_ARRAY,
                ],
            ];
        }

        return $eventSourcingStoreStructure;
    }

    /**
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildPresentationStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_REST] as $name => $controller) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_PRESENTATION][DataTypeInterface::STRUCTURE_TYPE_REST][$name] = $controller;
        }
        
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_RPC] as $name => $rpc) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_PRESENTATION][DataTypeInterface::STRUCTURE_TYPE_RPC][$name] = $rpc;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_CLI] as $name => $cli) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_PRESENTATION][DataTypeInterface::STRUCTURE_TYPE_CLI][$name] = $cli;
        }

        return $domainStructure;
    }

    protected function generateStructure(string $domainName, array $domainStructure, string $domainRootPath): void
    {
        $this->generateDomainLayerStructure($domainName, DataTypeInterface::STRUCTURE_LAYER_DOMAIN, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure($domainName, DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure($domainName, DataTypeInterface::STRUCTURE_LAYER_APPLICATION, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure($domainName, DataTypeInterface::STRUCTURE_LAYER_PRESENTATION, $domainStructure, $domainRootPath);
    }

    protected function generateDomainLayerStructure(string $domainName, string $domainLayer, array $domainStructure, string $domainRootPath): void
    {
        $domainLayerPath = $domainRootPath.DIRECTORY_SEPARATOR.ucfirst($domainLayer);

        if (!file_exists($domainLayerPath)) {
            if (!mkdir($domainLayerPath, 0755) && !is_dir($domainLayerPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $domainLayerPath));
            }
        }
        $classBuilder = new ClassBuilder($this->namespace, $domainLayerPath);

        foreach ($domainStructure[$domainLayer] as $type => $layer) {
            if (empty($layer)) {
                continue;
            }

            if ($type === DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK) {
                $layerFolder = ucfirst($this->underscoreAndHyphenToCamelCase(str_replace(DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK, DataTypeInterface::STRUCTURE_TYPE_COMMAND.DIRECTORY_SEPARATOR."Task", $type)));
            } elseif ($type === DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK) {
                $layerFolder = ucfirst($this->underscoreAndHyphenToCamelCase(str_replace(DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK, DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER.DIRECTORY_SEPARATOR."Task", $type)));
            } else {
                $layerFolder = ucfirst($this->underscoreAndHyphenToCamelCase(str_replace(["Interface", "interface"], "", $type)));
            }
            $layerPatternPath = $domainLayerPath.DIRECTORY_SEPARATOR.$layerFolder;

            if (!file_exists($layerPatternPath)) {
                if (!mkdir($layerPatternPath, 0755) && !is_dir($layerPatternPath)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $layerPatternPath));
                }
            }

            foreach ($layer as $name => $layerStructure) {
                if (
                    (
                        $type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY ||
                        $type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE
                    ) &&
                    (
                        $name === DataTypeInterface::STRUCTURE_TYPE_QUERY ||
                        $name === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE ||
                        $name === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_EVENT_SOURCIHNG_STORE ||
                        $name === DataTypeInterface::STRUCTURE_TYPE_READ_MODEL
                    )
                ) {
                    $layerRepositoryPatternPath = $layerPatternPath.DIRECTORY_SEPARATOR.ucfirst($name);

                    if (!file_exists($layerRepositoryPatternPath)) {
                        if (!mkdir($layerRepositoryPatternPath, 0755) && !is_dir($layerRepositoryPatternPath)) {
                            throw new \RuntimeException(sprintf('Directory "%s" was not created', $layerRepositoryPatternPath));
                        }
                    }
                    foreach ($layerStructure as $layerName => $structure) {
                        $classBuilder->generate($domainName, $domainLayer, $type, $name, $structure, $domainStructure, $layerRepositoryPatternPath);
                    }
                    continue;
                }
                $classBuilder->generate($domainName, $domainLayer, $type, $name, $layerStructure, $domainStructure, $layerPatternPath);
            }
        }
    }
}
