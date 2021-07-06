<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

use Exception;
use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;

/**
 * Class ProjectBuilder.
 *
 * @SuppressWarnings(PHPMD)
 */
class ProjectBuilder
{
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
            $domainName = ucfirst(strtolower($name));
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
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND])) {
            throw new Exception('no commands section');
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY])) {
            throw new Exception('no repositories section');
        }

        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER] = [];
        }
        if (!isset($domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER])) {
            $domainStructure[DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER] = [];
        }
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
            mkdir($domainRootPath, 0755);
        }
        $domainStructure = $this->buildStructure($domainStructure);
        $this->generateStructure($domainStructure, $domainRootPath);
    }

    protected function buildStructure(array $structure): array
    {
        $domainStructure = DataTypeInterface::DOMAIN_BASE_STRUCTURE;
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
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildDomainStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_COMMAND] as $name => $command) {
            if (!isset($command[DataTypeInterface::STRUCTURE_TYPE_ENTITY])) {
                throw new Exception(sprintf("Entity for command '%s' was not found!", $this->name));
            }
            $entity = $command[DataTypeInterface::STRUCTURE_TYPE_ENTITY];
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_COMMAND][$name] = $command;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_COMMAND][] = $name;

            foreach ($command[DataTypeInterface::STRUCTURE_TYPE_EVENT] as $name => $event) {
                $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_EVENT][$name] = [
                    DataTypeInterface::STRUCTURE_TYPE_ENTITY => $entity,
                    'args' => $event,
                ];
                $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_EVENT][] = $name;
            }
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_QUERY] as $name => $query) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_QUERY][$name] = $query;
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_FACTORY][DataTypeInterface::STRUCTURE_TYPE_QUERY][] = $name;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_ENTITY] as $name => $entity) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_ENTITY][$name] = $entity;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT] as $name => $valueObject) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT][$name] = $valueObject;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_REPOSITORY] as $name => $repository) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_REPOSITORY][$name] = $repository;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_SERVICE] as $name => $service) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_DOMAIN][DataTypeInterface::STRUCTURE_TYPE_SERVICE][$name] = $service;
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

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_SAGA] as $name => $event) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_SAGA][$name] = $event;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_PROJECTOR] as $name => $projector) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_PROJECTOR][$name] = $projector;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_DTO] as $name => $dto) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_APPLICATION][DataTypeInterface::STRUCTURE_TYPE_DTO][$name] = $dto;
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

    /**
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     *
     * @return mixed[]
     */
    protected function buildPresentationStructure(array $structure, array $domainStructure): array
    {
        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_RPC] as $name => $rpc) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_PRESENTATION][DataTypeInterface::STRUCTURE_TYPE_RPC][$name] = $rpc;
        }

        foreach ($structure[DataTypeInterface::STRUCTURE_TYPE_CLI] as $name => $cli) {
            $domainStructure[DataTypeInterface::STRUCTURE_LAYER_PRESENTATION][DataTypeInterface::STRUCTURE_TYPE_CLI][$name] = $cli;
        }

        return $domainStructure;
    }

    protected function generateStructure(array $domainStructure, string $domainRootPath): void
    {
        $this->generateDomainLayerStructure(DataTypeInterface::STRUCTURE_LAYER_DOMAIN, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure(DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure(DataTypeInterface::STRUCTURE_LAYER_APPLICATION, $domainStructure, $domainRootPath);
        $this->generateDomainLayerStructure(DataTypeInterface::STRUCTURE_LAYER_PRESENTATION, $domainStructure, $domainRootPath);
    }

    protected function generateDomainLayerStructure(string $domainLayer, array $domainStructure, string $domainRootPath): void
    {
        $domainLayerPath = $domainRootPath.DIRECTORY_SEPARATOR.ucfirst($domainLayer);

        if (!file_exists($domainLayerPath)) {
            mkdir($domainLayerPath, 0755);
        }
        $classBuilder = new ClassBuilder($this->namespace, $domainLayerPath);

        foreach ($domainStructure[$domainLayer] as $type => $layer) {
            if (empty($layer)) {
                continue;
            }
            $layerPatternPath =  $domainLayerPath.DIRECTORY_SEPARATOR.ucfirst($type);

            if (!file_exists($layerPatternPath)) {
                mkdir($layerPatternPath, 0755);
            }

            foreach ($layer as $name => $layerStructure) {
                $classBuilder->generate($domainLayer, $type, $name, $layerStructure, $domainStructure, $layerPatternPath);
            }
        }
    }
}
