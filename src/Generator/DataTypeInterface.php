<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

/**
 * Interface DataTypeInterface.
 */
interface DataTypeInterface
{
    public const BUILDER_STRUCTURE_TYPE         = "type";
    public const BUILDER_STRUCTURE_TYPE_ARGS    = "args";
    public const BUILDER_STRUCTURE_TYPE_METHODS = "methods";
    public const BUILDER_STRUCTURE_TYPE_RETURN  = "return";

    public const DATA_TYPE_INT = "int";
    public const DATA_TYPE_INTEGER = "integer";
    public const DATA_TYPE_FLOAT = "float";
    public const DATA_TYPE_MIXED = "mixed";
    public const DATA_TYPE_STRING = "string";
    public const DATA_TYPE_BOOL = "bool";
    public const DATA_TYPE_BOOLEAN = "boolean";
    public const DATA_TYPE_BOOL_TRUE = "true";
    public const DATA_TYPE_BOOL_FALSE = "false";
    public const DATA_TYPE_NULL = "null";
    public const DATA_TYPE_VOID = "void";
    public const DATA_TYPE_ARRAY = "array";
    public const DATA_TYPE_ARRAY_MIXED = "mixed[]";
    public const DATA_TYPE_CLOSURE = "\Closure";
    public const DATA_TYPE_CALLABLE = "callable";
    public const DATA_TYPE_THIS = "\$this";
    public const DATA_TYPE_SELF = "self";
    public const DATA_TYPE_STATIC = "static";
    public const DATA_TYPE_ = "";
    public const DATA_TYPE_UUID = "uuid";

    public const DATA_SCALAR_TYPE_INT = "int";
    public const DATA_SCALAR_TYPE_FLOAT = "float";
    public const DATA_SCALAR_TYPE_MIXED = "mixed";
    public const DATA_SCALAR_TYPE_ARRAY = "array";
    public const DATA_SCALAR_TYPE_STRING = "string";
    public const DATA_SCALAR_TYPE_BOOL = "bool";
    public const DATA_SCALAR_TYPE_DATETIME = "\DateTime";

    public const DATA_ORM_TYPE_INT = "integer";
    public const DATA_ORM_TYPE_FLOAT = "float";
    public const DATA_ORM_TYPE_JSON = "json";
    public const DATA_ORM_TYPE_STRING = "string";
    public const DATA_ORM_TYPE_BOOL = "boolean";
    public const DATA_ORM_TYPE_DATETIME = "datetime";
    public const DATA_ORM_TYPE_UUID = "uuid";
    public const DATA_ORM_TYPE_GUID = "guid";
    public const DATA_ORM_TYPE_UUID_BINARY = "uuid_binary";
    public const DATA_ORM_TYPE_UUID_BINARY_ORDERED_TIME = "uuid_binary_ordered_time";

    public const STRUCTURE_TYPE_COMMAND = "command";
    public const STRUCTURE_TYPE_COMMAND_HANDLER = "commandHandler";
    public const STRUCTURE_TYPE_COMMAND_TASK = "taskCommand";
    public const STRUCTURE_TYPE_COMMAND_HANDLER_TASK = "taskCommandHandler";
    public const STRUCTURE_TYPE_QUERY = "query";
    public const STRUCTURE_TYPE_QUERY_HANDLER = "queryHandler";
    public const STRUCTURE_TYPE_EVENT = "event";
    public const STRUCTURE_TYPE_ENTITY = "entity";
    public const STRUCTURE_TYPE_ENTITY_INTERFACE = "entityInterface";
    public const STRUCTURE_TYPE_READ_MODEL = "readModel";
    public const STRUCTURE_TYPE_READ_MODEL_INTERFACE = "readModelInterface";
    public const STRUCTURE_TYPE_FACTORY = "factory";
    public const STRUCTURE_TYPE_FACTORY_INTERFACE = "factoryInterface";
    public const STRUCTURE_TYPE_FACTORY_READ_MODEL = "readModelFactory";
    public const STRUCTURE_TYPE_FACTORY_COMMAND = "commandFactory";
    public const STRUCTURE_TYPE_FACTORY_EVENT = "eventFactory";
    public const STRUCTURE_TYPE_FACTORY_QUERY = "queryFactory";
    public const STRUCTURE_TYPE_FACTORY_ENTITY = "entityFactory";
    public const STRUCTURE_TYPE_FACTORY_VALUE_OBJECT = "valueObjectFactory";
    public const STRUCTURE_TYPE_SERVICE = "service";
    public const STRUCTURE_TYPE_SERVICE_INTERFACE = "serviceInterface";
    public const STRUCTURE_TYPE_DTO = "dto";
    public const STRUCTURE_TYPE_DTO_INTERFACE = "dtoInterface";
    public const STRUCTURE_TYPE_DTO_FACTORY = "dtoFactory";
    public const STRUCTURE_TYPE_DTO_FACTORY_INTERFACE = "dtoFactoryInterface";
    public const STRUCTURE_TYPE_VALUE_OBJECT = "valueObject";
    public const STRUCTURE_TYPE_SAGA = "saga";
    public const STRUCTURE_TYPE_PROJECTOR = "projector";
    public const STRUCTURE_TYPE_REPOSITORY = "repository";
    public const STRUCTURE_TYPE_REPOSITORY_READ_MODEL = "readModelStore";
    public const STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE = "entityStore";
    public const STRUCTURE_TYPE_REPOSITORY_QUERY_STORE = "queryStore";
    public const STRUCTURE_TYPE_REPOSITORY_EVENT_SOURCIHNG_STORE = "eventSourcingStore";
    public const STRUCTURE_TYPE_REPOSITORY_TASK = "task";
    public const STRUCTURE_TYPE_REPOSITORY_TASK_INTERFACE = "taskInterface";
    public const STRUCTURE_TYPE_REPOSITORY_INTERFACE = "repositoryInterface";
    public const STRUCTURE_TYPE_EXCEPTION = "exception";
    public const STRUCTURE_TYPE_MIGRATIONS = "migrations";
    public const STRUCTURE_TYPE_RPC = "rpc";
    public const STRUCTURE_TYPE_CLI = "cli";
    public const STRUCTURE_TYPE_REST = "rest";
    public const STRUCTURE_TYPE_COMMAND_BUS = "commandBus";

    public const STRUCTURE_LAYER_APPLICATION = "application";
    public const STRUCTURE_LAYER_DOMAIN = "domain";
    public const STRUCTURE_LAYER_INFRASTRUCTURE = "infrastructure";
    public const STRUCTURE_LAYER_PRESENTATION = "presentation";

    public const VALUE_OBJECT_NAMESPACE = "MicroModule\ValueObject";
    public const VALUE_OBJECT_NAMESPACE_COMMON = "MicroModule\Common\Domain\ValueObject";
    public const VALUE_OBJECT_TYPE_ENTITY = "value_object_entity";
    public const VALUE_OBJECT_TYPE_IDENTITY_UUID = "identity_uuid";
    public const VALUE_OBJECT_TYPE_STRING = "stringLiteral_stringLiteral";
    public const VALUE_OBJECT_TYPE_NUMBER_INTEGER = "number_integer";
    public const VALUE_OBJECT_TYPE_NUMBER_REAL = "number_real";
    public const VALUE_OBJECT_TYPE_LOGICAL_BOOL = "logical_boolean";
    public const VALUE_OBJECT_TYPE_STRUCTURE_COLLECTION = "structure_collection";
    public const VALUE_OBJECT_TYPE_STRUCTURE_DICTIONARY = "structure_dictionary";
    public const VALUE_OBJECT_TYPE_STRUCTURE_KEYVALUE = "structure_keyvalue";
    public const VALUE_OBJECT_TYPE_DATETIME_DATETIME = "dateTime_dateTime";
    public const VALUE_OBJECT_TYPE_DATETIME_DATE = "dateTime_date";
    public const VALUE_OBJECT_TYPE_DATETIME_TIME = "dateTime_time";
    public const VALUE_OBJECT_TYPE_PERSON_AGE = "person_age";
    public const VALUE_OBJECT_TYPE_PERSON_GENDER = "person_gender";
    public const VALUE_OBJECT_TYPE_PERSON_NAME = "person_name";
    public const VALUE_OBJECT_TYPE_FIND_CRITERIA = "find_criteria";

    public const PROPERTY_VISIBILITY_PUBLIC = "public";
    public const PROPERTY_VISIBILITY_PROTECTED = "protected";
    public const PROPERTY_VISIBILITY_PRIVATE = "private";
    public const PROPERTY_CONSTANT = "const";

    public const DOMAIN_BASE_STRUCTURE = [
        DataTypeInterface::STRUCTURE_LAYER_APPLICATION => [
            DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER => [],
            DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER => [],
            DataTypeInterface::STRUCTURE_TYPE_FACTORY => [],
            DataTypeInterface::STRUCTURE_TYPE_PROJECTOR => [],
            DataTypeInterface::STRUCTURE_TYPE_SAGA => [],
            DataTypeInterface::STRUCTURE_TYPE_SERVICE => [],
            DataTypeInterface::STRUCTURE_TYPE_DTO => [],
        ],

        DataTypeInterface::STRUCTURE_LAYER_DOMAIN => [
            DataTypeInterface::STRUCTURE_TYPE_ENTITY => [],
            DataTypeInterface::STRUCTURE_TYPE_ENTITY_INTERFACE => [],
            DataTypeInterface::STRUCTURE_TYPE_COMMAND => [
                
            ],
            DataTypeInterface::STRUCTURE_TYPE_QUERY => [],
            DataTypeInterface::STRUCTURE_TYPE_EVENT => [],
            DataTypeInterface::STRUCTURE_TYPE_SERVICE => [],
            DataTypeInterface::STRUCTURE_TYPE_SERVICE_INTERFACE => [],
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE => [],
            DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT => [],
            DataTypeInterface::STRUCTURE_TYPE_EXCEPTION => [],
            DataTypeInterface::STRUCTURE_TYPE_FACTORY => [
                DataTypeInterface::STRUCTURE_TYPE_ENTITY => [],
                DataTypeInterface::STRUCTURE_TYPE_COMMAND => [],
                DataTypeInterface::STRUCTURE_TYPE_QUERY => [],
                DataTypeInterface::STRUCTURE_TYPE_READ_MODEL => [],
                DataTypeInterface::STRUCTURE_TYPE_EVENT => [],
                DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT => [],
            ],

        ],

        DataTypeInterface::STRUCTURE_LAYER_INFRASTRUCTURE => [
            DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => [],
            DataTypeInterface::STRUCTURE_TYPE_SERVICE => [],
            DataTypeInterface::STRUCTURE_TYPE_MIGRATIONS => [],
        ],

        DataTypeInterface::STRUCTURE_LAYER_PRESENTATION => [
            DataTypeInterface::STRUCTURE_TYPE_RPC => [],
            DataTypeInterface::STRUCTURE_TYPE_CLI => [],
        ],
    ];

    public const DOMAIN_VALUE_OBJECT_TO_SCALAR_MAP = [
         self::VALUE_OBJECT_TYPE_IDENTITY_UUID => self::DATA_SCALAR_TYPE_STRING,
         self::VALUE_OBJECT_TYPE_STRING => self::DATA_SCALAR_TYPE_STRING,
         self::VALUE_OBJECT_TYPE_NUMBER_INTEGER => self::DATA_SCALAR_TYPE_INT,
         self::VALUE_OBJECT_TYPE_NUMBER_REAL => self::DATA_SCALAR_TYPE_FLOAT,
         self::VALUE_OBJECT_TYPE_STRUCTURE_COLLECTION => self::DATA_SCALAR_TYPE_ARRAY,
         self::VALUE_OBJECT_TYPE_STRUCTURE_DICTIONARY => self::DATA_SCALAR_TYPE_ARRAY,
         self::VALUE_OBJECT_TYPE_STRUCTURE_KEYVALUE => self::DATA_SCALAR_TYPE_ARRAY,
         self::VALUE_OBJECT_TYPE_DATETIME_DATETIME => self::DATA_SCALAR_TYPE_DATETIME,
         self::VALUE_OBJECT_TYPE_DATETIME_DATE => self::DATA_SCALAR_TYPE_DATETIME,
         self::VALUE_OBJECT_TYPE_DATETIME_TIME => self::DATA_SCALAR_TYPE_DATETIME,
         self::VALUE_OBJECT_TYPE_PERSON_AGE => self::DATA_SCALAR_TYPE_INT,
         self::VALUE_OBJECT_TYPE_PERSON_GENDER => self::DATA_SCALAR_TYPE_INT,
         self::VALUE_OBJECT_TYPE_PERSON_NAME => self::DATA_SCALAR_TYPE_STRING,
         self::VALUE_OBJECT_TYPE_ENTITY => self::DATA_SCALAR_TYPE_ARRAY,
         self::VALUE_OBJECT_TYPE_FIND_CRITERIA => self::DATA_SCALAR_TYPE_ARRAY,
         self::VALUE_OBJECT_TYPE_LOGICAL_BOOL => self::DATA_SCALAR_TYPE_BOOL,
    ];

    public const DATA_ORM_TYPE_SCALAR_MAPPING = [
         self::DATA_SCALAR_TYPE_INT => self::DATA_ORM_TYPE_INT,
         self::DATA_SCALAR_TYPE_FLOAT => self::DATA_ORM_TYPE_FLOAT,
         self::DATA_SCALAR_TYPE_MIXED => self::DATA_ORM_TYPE_STRING,
         self::DATA_SCALAR_TYPE_ARRAY => self::DATA_ORM_TYPE_JSON,
         self::DATA_SCALAR_TYPE_STRING => self::DATA_ORM_TYPE_STRING,
         self::DATA_SCALAR_TYPE_BOOL => self::DATA_ORM_TYPE_BOOL,
         self::DATA_SCALAR_TYPE_DATETIME => self::DATA_ORM_TYPE_DATETIME,
         self::DATA_TYPE_UUID => self::DATA_ORM_TYPE_UUID_BINARY,
    ];

    public const STRUCTURE_REPOSITORY_DATA_TYPES = [
        self::STRUCTURE_TYPE_REPOSITORY_EVENT_SOURCIHNG_STORE,
        self::STRUCTURE_TYPE_REPOSITORY_ENTITY_STORE,
        self::STRUCTURE_TYPE_REPOSITORY_READ_MODEL,
        self::STRUCTURE_TYPE_REPOSITORY_QUERY_STORE,
        self::STRUCTURE_TYPE_REPOSITORY_TASK,
        self::STRUCTURE_TYPE_REPOSITORY_TASK_INTERFACE,
    ];
    
    public const STRUCTURE_REPOSITORY_DATA_TYPES_MAPPING = [
        self::STRUCTURE_TYPE_REPOSITORY_READ_MODEL => self::STRUCTURE_TYPE_READ_MODEL,
        self::STRUCTURE_TYPE_REPOSITORY_QUERY_STORE => self::STRUCTURE_TYPE_QUERY,
    ];

    public const STRUCTURE_FACTORY_DATA_TYPES = [
        self::STRUCTURE_TYPE_FACTORY_READ_MODEL,
        self::STRUCTURE_TYPE_FACTORY_COMMAND,
        self::STRUCTURE_TYPE_FACTORY_EVENT,
        self::STRUCTURE_TYPE_FACTORY_ENTITY,
        self::STRUCTURE_TYPE_FACTORY_QUERY,
        self::STRUCTURE_TYPE_FACTORY_VALUE_OBJECT,
    ];
}
