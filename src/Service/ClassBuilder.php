<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Service;

use MicroModule\MicroserviceGenerator\Generator\DataTypeInterface;
use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;
use MicroModule\MicroserviceGenerator\Generator\Helper\CodeHelper;
use MicroModule\MicroserviceGenerator\Generator\Preprocessor\PreprocessorInterface;

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
     * @param string $name
     * @param string $type
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     * @param string $layerPatternPath
     *
     * @return bool
     *
     * @throws InvalidClassTypeException
     */
    public function generate(
        string $layer,
        string $type,
        string $name,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ): bool {
        if (
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_QUERY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_VALUE_OBJECT &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_EVENT &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_COMMAND_HANDLER &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_QUERY_HANDLER &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_SAGA &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_PROJECTOR &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_REPOSITORY &&
            $type !== DataTypeInterface::STRUCTURE_TYPE_ENTITY
        ) {
            return false;
        }

        return $this->generateFile($layer, $type, $name, $structure, $domainStructure, $layerPatternPath);
    }

    /**
     * Generate skeleton for source class.
     *
     * @param string $name
     * @param string $type
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     * @param string $layerPatternPath
     *
     * @throws InvalidClassTypeException
     */
    protected function generateFile(
        string $layer,
        string $type,
        string $name,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ): bool {
        $generatorClassName = $this->getClassGenerator($type);
        $generator = new $generatorClassName($layer, $type, $name, $this->projectNamespace, $structure, $domainStructure, $layerPatternPath);
        $className = $generator->getFullClassName();

        if(class_exists($className)) {
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
    protected function getClassGenerator(string $type): string
    {
        $classGenerator = 'MicroModule\MicroserviceGenerator\Generator\Type\\'.ucfirst($type).'Generator';

        if (!class_exists($classGenerator)) {
            throw new InvalidClassTypeException(sprintf('Generator \'%s\' does not exist.', $classGenerator));
        }

        return $classGenerator;
    }
}
