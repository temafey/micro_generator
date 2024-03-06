<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;
use MicroModule\MicroserviceGenerator\Generator\GeneratorInterface;
use MicroModule\MicroserviceGenerator\Generator\Helper\CodeHelper;
use MicroModule\MicroserviceGenerator\Generator\Preprocessor\PreprocessorInterface;
use PHPUnit\Exception;

/**
 * Class ClassBuilder.
 *
 * @SuppressWarnings(PHPMD)
 */
class ClassBuilder
{
    use CodeHelper;

    public const DEFAULT_SOURCE_FOLDER_NAME = 'src';
    public const PSR_NAMESPACE_TYPE_1 = 'psr-1';
    public const PSR_NAMESPACE_TYPE_4 = 'psr-4';

    /**
     * Psr namespace type.
     *
     * @var string
     */
    protected $psrNamespaceType;

    /**
     * Source code folder name.
     */
    protected string $sourceFolderName;

    /**
     * Source path.
     */
    protected string $sourcePath;

    /**
     * Local path for generated tests.
     *
     * @var string[]
     */
    protected array $localPath = [];

    /**
     * Method preprocessor closure.
     *
     * @var PreprocessorInterface[]
     */
    protected array $preprocessors = [];

    /**
     * TestClass constructor.
     *
     * @param string $projectNamespace
     * @param string $psrNamespaceType
     * @param string $sourceFolderName
     */
    public function __construct(
        string $projectNamespace,
        string $sourceFolderName = self::DEFAULT_SOURCE_FOLDER_NAME,
        string $psrNamespaceType = self::PSR_NAMESPACE_TYPE_4
    ) {
        $this->projectNamespace = $projectNamespace;
        $this->psrNamespaceType = $psrNamespaceType;
        $this->sourceFolderName = $sourceFolderName;
    }

    /**
     * Generate test.
     *
     * @throws InvalidClassTypeException
     */
    public function generate(
        string $domainName,
        string $layer,
        string $type,
        string $name,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ): bool {
        if (
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND_TASK &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_QUERY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_EVENT &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER_TASK &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_SAGA &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_PROJECTOR &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_REPOSITORY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_ENTITY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_ENTITY_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_READ_MODEL &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_READ_MODEL_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_FACTORY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_DTO &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_DTO_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_DTO_FACTORY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_DTO_FACTORY_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_REST
        ) {
            return false;
        }

        return $this->generateFile($domainName, $layer, $type, $name, $structure, $domainStructure, $layerPatternPath);
    }

    /**
     * Generate skeleton for source class.
     * 
     * @throws InvalidClassTypeException
     */
    protected function generateFile(
        string $domainName,
        string $layer,
        string $type,
        string $name,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ): bool {
        $generatorClassName = $this->getClassGenerator($type, $name);
        /** @var GeneratorInterface $generator */
        $generator = new $generatorClassName($domainName, $layer, $type, $name, $this->projectNamespace, $structure, $domainStructure, $layerPatternPath);
        $className = $generator->getFullClassName();

        try {
            if (@class_exists($className)) {
                return false;
            }
        } catch (\Throwable $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);

            return false;
        }
        $preprocessor = $this->getPreprocessor($className);

        if ($preprocessor) {
            $generator->setPreprocessor($preprocessor);
        }
        $generator->write();
        $this->localPath[] = $generator->getSourceFile();

        return true;
    }

    /**
     * Set preprocessor test generator.
     *
     * @param string $className
     *
     * @return PreprocessorInterface
     */
    public function getPreprocessor(string $className): ?PreprocessorInterface
    {
        return $this->preprocessors[$className] ?? null;
    }

    /**
     * Get preprocessor generator.
     *
     * @param string $className
     * @param PreprocessorInterface $preprocessor
     */
    public function setPreprocessor(string $className, PreprocessorInterface $preprocessor): void
    {
        $this->preprocessors[$className] = $preprocessor;
    }

    /**
     * Return generator class name by type.
     *
     * @throws InvalidClassTypeException
     */
    protected function getClassGenerator(string $type, string $name): string
    {
        if ($type === DataTypeInterface::STRUCTURE_TYPE_REPOSITORY_INTERFACE) {
            $classGenerator = 'MicroModule\MicroserviceGenerator\Generator\Type\Repository\\'.ucfirst($this->underscoreAndHyphenToCamelCase($name)).'InterfaceGenerator';
        } elseif ($type === DataTypeInterface::STRUCTURE_TYPE_FACTORY_INTERFACE) {
            $classGenerator = 'MicroModule\MicroserviceGenerator\Generator\Type\Factory\\'.ucfirst($this->underscoreAndHyphenToCamelCase($name)).'InterfaceGenerator';
        } else {
            $classGenerator = 'MicroModule\MicroserviceGenerator\Generator\Type\\'.ucfirst($type).'\\'.ucfirst($this->underscoreAndHyphenToCamelCase($name)).'Generator';
        }

        if (class_exists($classGenerator)) {
            return $classGenerator;
        }
        $classGenerator = 'MicroModule\MicroserviceGenerator\Generator\Type\\'.ucfirst($type).'Generator';

        if (!class_exists($classGenerator)) {
            throw new InvalidClassTypeException(sprintf('Generator \'%s\' does not exist.', $classGenerator));
        }

        return $classGenerator;
    }
}
