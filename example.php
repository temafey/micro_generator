<?php

use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Service\ProjectBuilder;

set_time_limit(0);

require 'vendor/autoload.php';

$structure = [
  'User' => [
      DataTypeInterface::STRUCTURE_TYPE_ENTITY => [
          'user' => [
              'register',
              'validate',
          ]
      ],

      DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT => [
          'process_uuid' => [
              'type' => DataTypeInterface::VALUE_OBJECT_TYPE_IDENTITY_UUID,
          ],
          'uuid' => [
              'type' => DataTypeInterface::VALUE_OBJECT_TYPE_IDENTITY_UUID,
          ],
          'name' => [
              'type' => DataTypeInterface::VALUE_OBJECT_TYPE_PERSON_NAME,
          ],
          'age' => [
              'type' => DataTypeInterface::VALUE_OBJECT_TYPE_PERSON_AGE,
          ],
          'user' => [
              'type' => DataTypeInterface::VALUE_OBJECT_TYPE_ENTITY,
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'name',
                  'age',
              ]
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_COMMAND => [
          'register' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'process_uuid',
                  'uuid',
                  'user',
              ],
              DataTypeInterface::STRUCTURE_TYPE_ENTITY => 'user',
              DataTypeInterface::STRUCTURE_TYPE_EVENT => [
                  'register' => [
                      'process_uuid',
                      'uuid',
                      'user',
                  ],
              ],
          ],
          'validate' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'process_uuid',
                  'uuid',
                  'user',
              ],
              DataTypeInterface::STRUCTURE_TYPE_ENTITY => 'user',
              DataTypeInterface::STRUCTURE_TYPE_EVENT => [
                  'validate' => [
                      'process_uuid',
                      'uuid',
                      'user',
                  ],
              ],
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER => [
          'register' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => 'command',
                  DataTypeInterface::STRUCTURE_TYPE_FACTORY => 'user',
              ],
              DataTypeInterface::STRUCTURE_TYPE_ENTITY => 'user',
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER => [
          'fetch-one' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => 'query',
              ],
              DataTypeInterface::STRUCTURE_TYPE_ENTITY => 'user',
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_QUERY => [
          'fetch-one' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'process_uuid',
                  'uuid',
              ],
              DataTypeInterface::STRUCTURE_TYPE_ENTITY => 'user',
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_SAGA => [
          'user' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'League\Tactician\CommandBus',
                  'MicroModule\Base\Domain\Factory\CommandFactoryInterface',
              ],
              DataTypeInterface::STRUCTURE_TYPE_EVENT => [
                  'register' => 'validate',
                  'validate' => true,
              ],
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_PROJECTOR => [
          'user' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'user' => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY,
                  'command' => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY,
                  'query' => DataTypeInterface::STRUCTURE_TYPE_REPOSITORY,
                  'League\Tactician\CommandBus',
                  'MicroModule\Base\Domain\Factory\CommandFactoryInterface',
              ],
              DataTypeInterface::STRUCTURE_TYPE_EVENT => [
                  'register'
              ],
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_REPOSITORY => [
          'query' => [
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                  'Broadway\EventStore\EventStore',
                  'Broadway\EventHandling\EventBus',
                  'eventStreamDecorators' => DataTypeInterface::DATA_TYPE_ARRAY,
              ],
              DataTypeInterface::BUILDER_STRUCTURE_TYPE_METHODS => [
                  'add' => [
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                          'uuid' => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                          'user' => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                      ],
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_VOID,
                  ],
                  'findByUuid' => [
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                          'uuid' => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                      ],
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_ARRAY,
                  ],
                  'findByCriteria' => [
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                          'FindCriteria' => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                      ],
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => DataTypeInterface::DATA_TYPE_ARRAY,
                  ],
                  'findByNickname' => [
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_ARGS => [
                          'nickname' => DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT,
                          'Poc\Micro\Poc\User\Rpc\Domain\Entity\UserReadInterface',
                      ],
                      DataTypeInterface::BUILDER_STRUCTURE_TYPE_RETURN => 'Poc\Micro\Poc\User\Rpc\Domain\Entity\UserReadInterface',
                  ],
              ]
          ],
      ],

      DataTypeInterface::STRUCTURE_TYPE_SERVICE => [
          'test' => [
              'func1' => [

              ],
              'func2' => [

              ],
              'func3' => [

              ],
              'func4' => [

              ],
          ],
      ],
  ]
];

$generatorProjectBuilder = new ProjectBuilder('/app/src', 'Poc\Micro', $structure);
$generatorProjectBuilder->generate();
