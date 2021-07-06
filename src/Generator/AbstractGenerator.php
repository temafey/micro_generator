<?php

declare(strict_types=1);

namespace MicroModule\MicroserviceGenerator\Generator;

use Exception;
use MicroModule\MicroserviceGenerator\Generator\Exception\InvalidClassTypeException;
use MicroModule\MicroserviceGenerator\Generator\Preprocessor\PreprocessorInterface;
use MicroModule\MicroserviceGenerator\Generator\Helper\CodeHelper;

/**
 * Generator for skeletons.
 *
 * @license http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 */
abstract class AbstractGenerator
{
    use CodeHelper;

    protected const CLASS_TEMPLATE_TYPE_DEFAULT         = "Class";
    protected const CLASS_TEMPLATE_TYPE_FULL            = "ClassFull";
    protected const CLASS_TEMPLATE_TYPE_ENTITY          = "ClassEntity";
    protected const CLASS_TEMPLATE_TYPE_VALUE_OBJECT    = "ClassValueObject";
    protected const METHOD_TEMPLATE_TYPE_DEFAULT        = "Method";
    protected const METHOD_TEMPLATE_TYPE_STATIC         = "MethodStatic";
    protected const METHOD_TEMPLATE_TYPE_BOOL           = "MethodBool";
    protected const METHOD_TEMPLATE_TYPE_INTERFACE      = "MethodInterface";
    protected const METHOD_TEMPLATE_TYPE_VOID           = "MethodVoid";
    protected const PROPERTY_TEMPLATE_TYPE_DEFAULT      = "Property";
    protected const INTERFACE_TEMPLATE_TYPE_DEFAULT     = "Interface";

    /**
     * Object name.
     */
    protected string $name;

    /**
     * Class DDD pattern type.
     */
    protected string $type;

    /**
     * Class DDD layer.
     */
    protected string $layer;

    /**
     * Project global namespace.
     */
    protected string $projectNamespace;

    /**
     * Class structure.
     * @var mixed[]
     */
    protected array $structure;

    /**
     * Project general structure.
     * @var mixed[]
     */
    protected array $domainStructure;

    /**
     * Project layer path.
     */
    protected string $layerPatternPath;

    /**
     * Full class path.
     */
    protected string $sourceFile;

    /**
     * Array of use statetement.
     * @var string[]
     */
    protected array $useStatement = [];

    /**
     * Method preprocessor closure.
     *
     * @var PreprocessorInterface
     */
    protected $preprocessor;

    /**
     * Constructor.
     *
     * @param string $layer
     * @param string $type
     * @param string $name
     * @param string $projectNamespace
     * @param mixed[] $structure
     * @param mixed[] $domainStructure
     * @param string $layerPatternPath
     */
    public function __construct(
        string $layer,
        string $type,
        string $name,
        string $projectNamespace,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ) {
        $this->layer = $layer;
        $this->type = $type;
        $this->name = $name;
        $this->projectNamespace = $projectNamespace;
        $this->structure = $structure;
        $this->domainStructure = $domainStructure;
        $this->layerPatternPath = $layerPatternPath;
        $this->sourceFile = $layerPatternPath.DIRECTORY_SEPARATOR.$this->getShortClassName($name, $type).".php";
    }

    /**
     * Return full name of class that could be generated.
     * @throws InvalidClassTypeException
     */
    public function getFullClassName(): string
    {
        return $this->getClassName($this->name, $this->type);
    }

    /**
     * Return source class path.
     */
    public function getSourceFile(): string
    {
        return $this->sourceFile;
    }

    /**
     * Generates the code and writes it to a source file.
     *
     * @param string $file
     *
     * @return bool
     */
    public function write(): bool
    {
        if (file_exists($this->sourceFile)) {
            echo "Class '".$this->sourceFile."' already exists.".PHP_EOL;

            return false;
        }
        $code = $this->generate();

        if (null === $code) {
            echo "Class was not created for file '".$this->sourceFile."'.".PHP_EOL;

            return false;
        }

        if (file_put_contents($this->sourceFile, $code)) {
            echo "Class '".$this->sourceFile."' created.".PHP_EOL;

            return true;
        }
        echo "Class was not created for file '".$this->sourceFile."'.".PHP_EOL;

        return false;
    }

    /**
     * Render class.
     *
     * @throws InvalidClassTypeException
     */
    protected function renderClass(
        string $template,
        string $classNamespace,
        array $useStatement,
        string $extends,
        array $implements,
        array $useTraits,
        array $properties,
        array $methods
    ): string {
        $template = new Template(
            sprintf(
                "%s%stemplate%s%s.tpl",
                realpath(__DIR__),
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $template
            )
        );
        sort($useStatement);
        $shortClassName = $this->getShortClassName($this->name, $this->type);

        if ($extends) {
            $extends = " extends ".$extends;
        }
        $implements = implode(', ', $implements);

        if ($implements) {
            $implements = " implements ".$implements;
        }
        $template->setVar(
            [
                "namespace" => $classNamespace,
                "className" => $shortClassName,
                "extends" => $extends,
                "implements" => $implements,
                "fullClassName" => $this->getClassName($this->name, $this->type),
                "useStatement" => implode("", $useStatement),
                "useTraits" => implode(" ,", $useTraits),
                "properties" => implode("", $properties),
                "methods" => implode("", $methods),
                "date" => date("Y-m-d"),
                "time" => date("H:i:s"),
            ]
        );

        return $template->render();
    }

    /**
     * Render interface.
     *
     * @throws InvalidClassTypeException
     */
    protected function renderInterface(
        string $template,
        string $classNamespace,
        array $useStatement,
        array $methods
    ): string {
        $template = new Template(
            sprintf(
                "%s%stemplate%s%s.tpl",
                realpath(__DIR__),
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $template
            )
        );
        sort($useStatement);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, $this->type);
        $template->setVar(
            [
                "namespace" => $classNamespace,
                "interfaceName" => $shortInterfaceName,
                "useStatement" => implode("", $useStatement),
                "methods" => implode("", $methods),
                "date" => date("Y-m-d"),
                "time" => date("H:i:s"),
            ]
        );

        return $template->render();
    }

    /**
     * Render class property.
     *
     * @throws Exception
     */
    protected function renderProperty(
        string $template,
        string $propertyComment,
        string $propertyVisibility,
        string $propertyType,
        string $propertyName,
        string $defaultValue = ""
    ): string {
        if ($propertyType !== DataTypeInterface::PROPERTY_CONSTANT) {
            $propertyName = "$".$propertyName;
        }
        $template = new Template(
            sprintf(
                "%s%stemplate%s%s.tpl",
                realpath(__DIR__),
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $template
            )
        );

        if ($defaultValue) {
            $defaultValue = " = ".$defaultValue;
        }
        if ($defaultValue === DataTypeInterface::DATA_TYPE_NULL) {
            $propertyType = "?".$propertyType;
        }
        $template->setVar(
            [
                "propertyComment" => $propertyComment,
                "propertyVisibility" => $propertyVisibility,
                "propertyName" => $propertyName,
                "propertyType" => $propertyType,
                "propertyDefault" => $defaultValue
            ]
        );

        return $template->render();
    }

    /**
     * Render class method.
     *
     * @throws Exception
     */
    protected function renderMethod(
        string $template,
        string $methodComment,
        string $methodName,
        string $arguments,
        string $returnType,
        string $methodLogic,
        string $return
    ): string {
        $template = new Template(
            sprintf(
                "%s%stemplate%s%s.tpl",
                realpath(__DIR__),
                DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $template
            )
        );

        if ($this->preprocessor) {
            //$this->preprocessor->process($class, $method, $methodName, $additional);
        }

        if ($returnType) {
            $returnType = ": ".$returnType;
        }
        if ($return) {
            $return = "return ".$return.";";
        }

        if ($methodLogic && $returnType) {
            $methodLogic .= "\r\n";
        }

        $template->setVar(
            [
                "methodComment" => $methodComment,
                "methodName" => $methodName,
                "arguments" => $arguments,
                "returnType" => $returnType,
                "methodLogic" => $methodLogic,
                "return" => $return,
            ]
        );

        return $template->render();
    }

    /**
     * Add new use statement to generator.
     */
    protected function addUseStatement(string $useStatement): void
    {
        if (!strpos($useStatement, "use ")) {
            $useStatement = "\r\nuse ".$useStatement;
        }
        if ($useStatement[-1] !== ";") {
            $useStatement .= ";";
        }

        if (in_array($useStatement, $this->useStatement)) {
            return;
        }
        $this->useStatement[] = $useStatement;
    }

    /**
     * Set preprocessor test generator.
     *
     * @return PreprocessorInterface
     */
    public function getPreprocessor(): PreprocessorInterface
    {
        return $this->preprocessor;
    }

    /**
     * Set preprocessor generator.
     *
     * @param PreprocessorInterface $preprocessor
     */
    public function setPreprocessor(PreprocessorInterface $preprocessor): void
    {
        $this->preprocessor = $preprocessor;
    }

    /**
     * Generate test code.
     *
     * @return string|null
     */
    abstract public function generate(): ?string;
}
