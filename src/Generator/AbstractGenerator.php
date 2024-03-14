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
abstract class AbstractGenerator implements GeneratorInterface
{
    use CodeHelper;

    protected const CLASS_TEMPLATE_TYPE_DEFAULT             = "Class";
    protected const CLASS_TEMPLATE_TYPE_FULL                = "ClassFull";
    protected const CLASS_TEMPLATE_TYPE_ENTITY              = "ClassEntity";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_ENTITY      = "ClassFactoryEntity";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_ENTITY_INTERFACE = "ClassFactoryEntityInterface";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_COMMAND      = "ClassFactoryCommand";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_COMMAND_INTERFACE = "ClassFactoryCommandInterface";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_QUERY      = "ClassFactoryQuery";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_QUERY_INTERFACE = "ClassFactoryQueryInterface";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_READ_MODEL     = "ClassFactoryReadModel";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_READ_MODEL_INTERFACE = "ClassFactoryReadModelInterface";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_DTO      = "ClassFactoryDto";
    protected const CLASS_TEMPLATE_TYPE_FACTORY_DTO_INTERFACE = "ClassFactoryDtoInterface";
    protected const CLASS_TEMPLATE_TYPE_READ_MODEL          = "ClassReadModel";
    protected const CLASS_TEMPLATE_TYPE_READ_MODEL_INTERFACE= "ClassReadModelInterface";
    protected const CLASS_TEMPLATE_TYPE_DTO_INTERFACE = "ClassDtoInterface";
    protected const CLASS_TEMPLATE_TYPE_VALUE_OBJECT        = "ClassValueObject";
    protected const CLASS_TEMPLATE_REPOSITORY_ENTITY_STORE  = "repository/ClassEntityStoreRepository";
    protected const CLASS_TEMPLATE_REPOSITORY_ENTITY_STORE_INTERFACE  = "repository/ClassEntityStoreInterfaceRepository";
    protected const CLASS_TEMPLATE_REPOSITORY_EVENT_SOURCING_STORE  = "repository/ClassEventSourcingStoreRepository";
    protected const CLASS_TEMPLATE_REPOSITORY_READ_MODEL    = "repository/ClassEventSourcingStoreRepository";
    protected const CLASS_TEMPLATE_REPOSITORY_TASK          = "repository/ClassTaskRepository";
    protected const METHOD_TEMPLATE_TYPE_DEFAULT            = "Method";
    protected const METHOD_TEMPLATE_TYPE_STATIC             = "MethodStatic";
    protected const METHOD_TEMPLATE_TYPE_BOOL               = "MethodBool";
    protected const METHOD_TEMPLATE_TYPE_INTERFACE          = "MethodInterface";
    protected const METHOD_TEMPLATE_TYPE_VOID               = "MethodVoid";
    protected const METHOD_TEMPLATE_TYPE_FIND_BY_UUID       = "MethodFindByUuid";
    protected const METHOD_TEMPLATE_TYPE_FIND_ONE_BY        = "MethodFindOneBy";
    protected const METHOD_TEMPLATE_TYPE_FIND_BY_CRITERIA   = "MethodFindByCriteria";
    protected const METHOD_TEMPLATE_TYPE_READ_MODEL         = "MethodReadModel";
    protected const METHOD_TEMPLATE_TYPE_READ_MODEL_ADD     = "MethodReadModelAdd";
    protected const METHOD_TEMPLATE_TYPE_TASK               = "MethodTask";
    protected const METHOD_TEMPLATE_TYPE_FACTORY            = "MethodFactory";
    protected const METHOD_TEMPLATE_TYPE_CONTROLLER_ACTION  = "MethodControllerAction";
    protected const METHOD_TEMPLATE_TYPE_ORM_COLUMN         = "MethodOrmColumn";
    protected const METHOD_TEMPLATE_TYPE_CONTROLLER_QA_PARAMETR  = "MethodControllerQaParametr";
    protected const PROPERTY_TEMPLATE_TYPE_DEFAULT          = "Property";
    protected const PROPERTY_TEMPLATE_TYPE_ANNOTATION       = "PropertyAnnotation";
    protected const INTERFACE_TEMPLATE_TYPE_DEFAULT         = "Interface";

    public const METHOD_TYPE_FIND_BY_UUID                   = "findByUuid";
    public const METHOD_TYPE_FIND_ONE_BY                    = "findOneBy";
    public const METHOD_TYPE_FIND_BY_CRITERIA               = "findByCriteria";
    public const METHOD_TYPE_FIND_ALL                       = "findAll";

    /**
     * Domain name.
     */
    protected string $domainName;

    /**
     * Class DDD layer.
     */
    protected string $layer;

    /**
     * Object name.
     */
    protected string $name;

    /**
     * Class DDD pattern type.
     */
    protected string $type;

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
     * Array of class rendered properties.
     * @var string[]
     */
    protected array $properties = [];

    /**
     * Constructor method arguments.
     * @var string[]
     */
    protected $constructArguments = [];

    /**
     * Constructor method arguments assignment.
     * @var string[]
     */
    protected $constructArgumentsAssignment = [];

    /**
     * Method preprocessor closure.
     */
    protected ?PreprocessorInterface $preprocessor = null;

    /**
     * Use common abstract component.
     */
    protected bool $useCommonComponent = true;

    /**
     * Additional template variables.
     * @var <string, mixed>
     */
    protected array $additionalVariables = [];

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
        string $domainName,
        string $layer,
        string $type,
        string $name,
        string $projectNamespace,
        array $structure,
        array $domainStructure,
        string $layerPatternPath
    ) {
        $this->domainName = $domainName;
        $this->layer = $layer;
        $this->type = $type;
        $this->name = $name;
        $this->projectNamespace = $projectNamespace;
        $this->structure = $structure;
        $this->domainStructure = $domainStructure;
        $this->layerPatternPath = $layerPatternPath;
        $this->setSourceFile();
        $this->initAdditionalVariables();
    }

    /**
     * Set source file full path.
     */
    protected function setSourceFile(): void
    {
        $this->sourceFile = $this->layerPatternPath.DIRECTORY_SEPARATOR.$this->getShortClassName($this->name , $this->type).".php";
    }

    /**
     * Initializes additional variables to use in template rendering.
     */
    protected function initAdditionalVariables(): void
    {
        $this->additionalVariables['classNamespace'] = $this->getClassNamespace($this->type);
        $this->additionalVariables['shortClassName'] = $this->getShortClassName($this->name, $this->type);
        $this->additionalVariables['shortInterfaceName'] = $this->getShortInterfaceName($this->name, $this->type);
        $this->additionalVariables['shortEntityName'] = $this->getEntityName();
        $this->additionalVariables['shortEntityInterfaceName'] = $this->getEntityName();

        if (strpos($this->additionalVariables['shortEntityInterfaceName'], "Interface") === false)  {;
            $this->additionalVariables['shortEntityInterfaceName'] .= "Interface";
        }
        $this->additionalVariables['shortValueObjectName'] = $this->getEntitValueObjectClassName();
    }

    /**
     * Return main entity short class name.
     */
    protected function getEntityName(): string
    {
        return $this->getShortClassName($this->domainName, DataTypeInterface::STRUCTURE_TYPE_ENTITY);
    }

    /**
     * Return main entity short class name.
     */
    protected function getEntitValueObjectClassName(): string
    {
        return $this->getValueObjectShortClassName($this->domainName);
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
        array $methods,
        array $additionalVariables = []
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
            array_merge(
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
                ],
                $this->additionalVariables,
                $additionalVariables
            )
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
        array $methods,
        string $extends = "",
        array $additionalVariables = []
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

        if ($extends) {
            $extends = " extends ".$extends;
        }
        sort($useStatement);
        $shortInterfaceName = $this->getShortInterfaceName($this->name, $this->type);
        $template->setVar(
            array_merge(
                [
                    "namespace" => $classNamespace,
                    "interfaceName" => $shortInterfaceName,
                    "useStatement" => implode("", $useStatement),
                    "extends" => $extends,
                    "methods" => implode("", $methods),
                    "date" => date("Y-m-d"),
                    "time" => date("H:i:s"),
                ],
                $this->additionalVariables,
                $additionalVariables
            )
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
        string $defaultValue = "",
        array $additionalVariables = []
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
            array_merge(
                [
                    "propertyComment" => $propertyComment,
                    "propertyVisibility" => $propertyVisibility,
                    "propertyName" => $propertyName,
                    "propertyType" => $propertyType,
                    "propertyDefault" => $defaultValue
                ],
                $this->additionalVariables,
                $additionalVariables
            )
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
        string $return,
        array $additionalVariables = [],
        bool $returnNull = false
    ): string {
        $template = new Template(
            sprintf(
                "%s%stemplate/method%s%s.tpl",
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
            $returnType = ": ".($returnNull && $returnType !== DataTypeInterface::DATA_TYPE_VOID ? "?" : "").$returnType;
        }

        if ($return) {
            $return = "return ".$return.";";
        }
        
        if ($methodLogic && $returnType) {
            $methodLogic .= "\r\n";
        }

        if (substr($methodName, 0, 2) !== '__') {
            $methodName = $this->underscoreAndHyphenToCamelCase($methodName);
        }
        $template->setVar(
            array_merge(
                [
                    "methodComment" => $methodComment,
                    "methodName" => $methodName,
                    "arguments" => $arguments,
                    "returnType" => $returnType,
                    "methodLogic" => $methodLogic,
                    "return" => $return,
                ],
                $this->additionalVariables,
                $additionalVariables
            )
        );

        return $template->render();
    }

    /**
     * Render interface method.
     *
     * @throws Exception
     */
    protected function renderMethodInterface(
        string $methodComment,
        string $methodName,
        string $arguments,
        string $returnType,
        string $template = self::METHOD_TEMPLATE_TYPE_INTERFACE,
        array $additionalVariables = []
    ): string {
        $template = new Template(
            sprintf(
                "%s%stemplate/method%s%s.tpl",
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

        if (substr($methodName, 0, 2) !== '__') {
            $methodName = $this->underscoreAndHyphenToCamelCase($methodName);
        }
        $template->setVar(
            array_merge(
                [
                    "methodComment" => $methodComment,
                    "methodName" => $methodName,
                    "arguments" => $arguments,
                    "returnType" => $returnType,
                ],
                $this->additionalVariables,
                $additionalVariables
            )
        );

        return $template->render();
    }

    /**
     * Add new use statement to code generator process.
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
     * Add new rendered property to code generator process.
     */
    protected function addProperty(
        string $name, 
        string $type, 
        string $comment = "",
        string $defaultValue = "",
        string $template = self::PROPERTY_TEMPLATE_TYPE_DEFAULT,
        string $visibility = DataTypeInterface::PROPERTY_VISIBILITY_PROTECTED
    ): void {
        if (isset($this->properties[$name])) {
            return;
        }
        $this->properties[$name] = $this->renderProperty(
            $template,
            $comment,
            $visibility,
            $type,
            $name,
            $defaultValue
        );
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
