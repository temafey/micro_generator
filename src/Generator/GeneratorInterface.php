<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;

/**
 * Interface GeneratorInterface.
 */
interface GeneratorInterface
{
    public const KEY_UNIQUE_UUID = 'uuid';
    public const KEY_UNIQUE_ID = 'id';
    public const KEY_UNIQUE_PROCESS_UUID = 'process_uuid';
    public const KEY_FIND_CRITERIA = 'find_criteria';
    public const KEY_OFFSET = 'offset';
    public const KEY_LIMIT = 'limit';
    public const KEY_CREATED_AT = 'created_at';
    public const KEY_UPDATED_AT = 'updated_at';
    public const VALUE_OBJECT_UNIQUE_UUID = 'Uuid';
    public const VALUE_OBJECT_UNIQUE_ID = 'Id';
    public const VALUE_OBJECT_UNIQUE_PROCESS_UUID = 'ProcessUuid';
    public const CREATE_METHOD_GET_PREFIX = "get";
    public const CREATE_METHOD_ADD_PREFIX = "add";
    public const CREATE_METHOD_INSERT_PREFIX = "insert";
    public const CREATE_METHOD_CREATE_PREFIX = "create";
    public const CREATE_METHOD_REMOVE_PREFIX = "remove";
    public const CREATE_METHOD_DELETE_PREFIX = "delete";

    public const CREATE_METHOD_NAME_PREFIXES = [
        self::CREATE_METHOD_ADD_PREFIX,
        self::CREATE_METHOD_INSERT_PREFIX,
        self::CREATE_METHOD_CREATE_PREFIX,
    ];
    
    public const UNIQUE_KEYS = [
        self::KEY_UNIQUE_PROCESS_UUID,
        self::KEY_UNIQUE_UUID,
        //self::KEY_UNIQUE_ID,
    ];

    public const COMMON_VALUE_OBJECT_KEYS = [
        self::KEY_UNIQUE_PROCESS_UUID, 
        self::KEY_UNIQUE_UUID, 
        self::KEY_UNIQUE_ID,
        self::KEY_FIND_CRITERIA,
        self::KEY_OFFSET,
        self::KEY_LIMIT,
        self::KEY_CREATED_AT,
        self::KEY_UPDATED_AT,
    ];

    /**
     * Return full name of class that could be generated.
     * @throws InvalidClassTypeException
     */
    public function getFullClassName(): string;

    /**
     * Return source class path.
     */
    public function getSourceFile(): string;

    /**
     * Generates the code and writes it to a source file.
     *
     * @return bool
     */
    public function write(): bool;
}
