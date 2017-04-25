<?php

require_once (__DIR__. '/TypescriptGeneratorBase.php');
require_once (__DIR__. '/GeneratedFileData.php');

class ClassesGenerator extends TypescriptGeneratorBase
{

    function __construct($serverMetadata)
    {
        parent::__construct($serverMetadata);
    }

    public function generate()
    {
        foreach ($this->serverMetadata->services as $service) {
            foreach ($service->actions as $serviceAction) {
                $result[] = $this->createServiceActionFile($service, $serviceAction);
            }
        }

        foreach ($this->serverMetadata->classTypes as $class) {
            $result[] = $this->createClassFile($class);
        }

        $result[] = $this->createRequestBaseFile();

        return $result;
    }



    function createRequestBaseFile()
    {
        $createClassArgs = new stdClass();
        $createClassArgs->name = "KalturaRequestBase";
        $createClassArgs->description = "";
        $createClassArgs->base = "KalturaObjectBase";
        $createClassArgs->basePath = "./";
        $createClassArgs->enumPath = "../enum/";
        $createClassArgs->typesPath = "./class/";
        $createClassArgs->importedItems = array();
        $createClassArgs->properties = $this->serverMetadata->requestSharedParameters;
        $createClassArgs->requireDataInCtor = true;

        $classBody = $this->createClassExp($createClassArgs);

        $fileContent = "{$this->getBanner()}
import { KalturaObjectMetadata } from './kaltura-object-base';
{$classBody}
";

        $file = new GeneratedFileData();
        $file->path = "kaltura-request-base.ts";
        $file->content = $fileContent;
        $result[] = $file;
        return $file;
    }

    function createClassFile(ClassType $class)
    {
        $createClassArgs = new stdClass();
        $createClassArgs->name = $class->name;
        $createClassArgs->description = $class->description;
        $createClassArgs->base = $class->base ? $class->base : 'KalturaObjectBase';
        $createClassArgs->basePath = $class->base ? "./" : "../";
        $createClassArgs->enumPath = "../enum/";
        $createClassArgs->typesPath = "./";
        $createClassArgs->properties = $class->properties;
        $createClassArgs->importedItems = array();
        $createClassArgs->customMetadataProperties[] = $this->createMetadataProperty('objectType',false,KalturaServerTypes::Simple,'constant', $class->name);
        $createClassArgs->requireDataInCtor = false;

        $classBody = $this->createClassExp($createClassArgs);

        $classFunctionName = ucfirst($class->name);
        $fileContent = "{$this->getBanner()}
import { KalturaObjectMetadata } from '../kaltura-object-base';
import { KalturaTypesFactory } from '../kaltura-types-factory';
{$classBody}
KalturaTypesFactory.registerType('$class->name',$classFunctionName);
";

        $file = new GeneratedFileData();
        $fileName = $this->utils->toLispCase($class->name);
        $file->path = "class/{$fileName}.ts";
        $file->content = $fileContent;
        $result[] = $file;
        return $file;
    }

    function createServiceActionFile(Service $service,ServiceAction $serviceAction)
    {
        $className = $service->name . ucfirst($serviceAction->name) . "Action";
        if ($serviceAction->resultType === KalturaServerTypes::File) {
            $actionNG2ResultType = "string";
        }else {
            $actionNG2ResultType = $this->toNG2TypeExp($serviceAction->resultType, $serviceAction->resultClassName);
        }

        $importedItems = array($className,'KalturaRequest');

        $getImportExpForTypeArgs = new stdClass();
        $getImportExpForTypeArgs->enumPath = "../enum/";
        $getImportExpForTypeArgs->typesPath = "../class/";
        $getImportExpForTypeArgs->type = $serviceAction->resultType;
        $getImportExpForTypeArgs->typeClassName = $serviceAction->resultClassName;
        $importResultType = $this->getImportExpForType($getImportExpForTypeArgs,$importedItems);
        if ($importResultType) {
            // prevent duplicate import for the result class
            $importedItems[] = $serviceAction->resultClassName;
        }

        $createClassArgs = new stdClass();
        $createClassArgs->name = $className;
        $createClassArgs->description = $serviceAction->description;
        $createClassArgs->basePath = "../";
        $createClassArgs->enumPath = "../enum/";
        $createClassArgs->typesPath = "../class/";
        $createClassArgs->properties = $serviceAction->params;
        $createClassArgs->importedItems = &$importedItems;
        $createClassArgs->customMetadataProperties[] = $this->createMetadataProperty('service',false,KalturaServerTypes::Simple,'constant', $service->id);
        $createClassArgs->customMetadataProperties[] = $this->createMetadataProperty('action',false,KalturaServerTypes::Simple,'constant', $serviceAction->name);

        if ($this->hasFileProperty($serviceAction->params))
        {
            $createClassArgs->base = "KalturaUploadRequest<{$actionNG2ResultType}>";
        }else {
            $createClassArgs->base = "KalturaRequest<{$actionNG2ResultType}>";
        }

        $resultType = $this->toApplicationType($serviceAction->resultType, $serviceAction->resultClassName);
        $createClassArgs->superArgs = "'{$resultType->type}', '{$resultType->subType}'";
        $createClassArgs->requireDataInCtor = $this->hasRequiredProperty($serviceAction->params);

        $classBody = $this->createClassExp($createClassArgs);


        $fileContent = "{$this->getBanner()}
import { KalturaObjectMetadata } from '../kaltura-object-base';
{$importResultType}

{$classBody}
";

        $file = new GeneratedFileData();
        $fileName = $this->utils->toLispCase($className);
        $file->path = "action/{$fileName}.ts";
        $file->content = $fileContent;
        $result[] = $file;
        return $file;
    }

    function createClassExp($args)
    {
        $name = $args->name;
        $description = $args->description;
        $base = isset($args->base) ? $args->base : null;
        $strippedBase = isset($base) ? preg_replace('/<.+>/i','',$base) : null;
        $basePath = isset($args->basePath) ? $args->basePath : null;
        $customMetadataProperties = isset($args->customMetadataProperties) ? $args->customMetadataProperties : array();
        $classTypeName = Utils::upperCaseFirstLetter($name);
        $desc = $description;
        $superArgs = isset($args->superArgs) ? $args->superArgs : '';
        $requireDataInCtor = $args->requireDataInCtor;

        $importedItems = &$args->importedItems;
        $importedItems[] = $name;

        // enrich super args
        if ($superArgs === '')
        {
            $superArgs = 'data';
        }else
        {
            $superArgs =  'data, ' . $superArgs;
        }

        $baseImport = null;
        if ($strippedBase && $basePath)
        {
            $importedItems[] = $strippedBase;
            $importFilePath = $basePath . utils::toLispCase($strippedBase);
            $baseImport = "import { {$strippedBase}, {$strippedBase}Args } from '{$importFilePath}';";
        }

        $aggregatedData = $this->aggregateClassData($args);

        if ($baseImport) {
            $aggregatedData->imports[] = $baseImport;
        }

        $classMetadataProperties = array_merge(
            $customMetadataProperties,
            $aggregatedData->propertiesMetadata
        );

        $result = "{$this->utils->buildExpression($aggregatedData->imports,NewLine)}

export interface {$classTypeName}Args {$this->utils->ifExp($strippedBase, " extends " . $strippedBase . "Args","")} {
    {$this->utils->buildExpression($aggregatedData->constructorArgs, NewLine, 1)}
}

{$this->utils->createDocumentationExp('',$desc)}
export class {$classTypeName} extends {$this->utils->ifExp($base, $base,'')} {

    {$this->utils->buildExpression($aggregatedData->classProperties, NewLine, 1)}

    constructor(data{$this->utils->ifExp($requireDataInCtor,"","?")} : {$classTypeName}Args)
    {
        super({$superArgs});
    }

    protected _getMetadata() : KalturaObjectMetadata
    {
        const result = super._getMetadata();
        Object.assign(
            result.properties,
            {
                {$this->utils->buildExpression($classMetadataProperties,',' . NewLine,4)}
            }
        );
        return result;
    }
}
";

        return $result;
    }


    function aggregateClassData($args)
    {
        $typesPath = $args->typesPath;
        $enumPath = $args->enumPath;
        $properties = $args->properties;
        $importedItems = &$args->importedItems;

        $result = new stdClass();
        $result->constructorArgs = array();
        $result->classProperties = array();
        $result->propertiesMetadata = array();
        $result->imports = array();
        $result->buildContent = array();
        $result->constructorContent = array();

        if (count($properties) != 0)
        {
            foreach($properties as $property) {
                if ($property->type === KalturaServerTypes::File) {
                    $ng2ParamType = "File";
                }else {
                    $ng2ParamType = $this->toNG2TypeExp($property->type, $property->typeClassName);
                }
                $default = $this->toNG2DefaultByType($property->type, $property->typeClassName, $property->default);
                $readOnly = isset($property->readOnly) && $property->readOnly;

                // update the properties declaration
                if (!$readOnly) {
                    $isOptional = isset($property->optional) && $property->optional;
                    $result->constructorArgs[] = "{$property->name}{$this->utils->ifExp($isOptional,"?","")} : {$ng2ParamType};";
                }

                $result->classProperties[] = ($readOnly ? "readonly " : "") . "{$property->name} : {$ng2ParamType}" . ($default ? " = {$default}" : "") . ";";
                $result->propertiesMetadata[] = $this->createMetadataProperty($property->name, isset($property->readOnly) ? $property->readOnly : false,$property->type,$property->typeClassName);
                $getImportExpForTypeArgs = new stdClass();
                $getImportExpForTypeArgs->enumPath = $enumPath;
                $getImportExpForTypeArgs->typesPath = $typesPath;
                $getImportExpForTypeArgs->type = $property->type;
                $getImportExpForTypeArgs->typeClassName = $property->typeClassName;
                $propertyImport = $this->getImportExpForType($getImportExpForTypeArgs, $importedItems);
                if ($propertyImport)
                {
                    $result->imports[] = $propertyImport;
                }
            }
        }

        return $result;
    }

    private function createMetadataProperty($name, $readOnly, $type, $typeClassName, $defaultValue = null)
    {
        $readOnlyExp = (isset($readOnly) && $readOnly) ? ', readOnly : true' : '';
        $defaultValueExp = isset($defaultValue) ? ", default : '{$defaultValue}'" : '';
        $propertyMetadataType = $this->toApplicationType($type, $typeClassName);
        return "{$name} : { type : '{$propertyMetadataType->type}' {$defaultValueExp} {$readOnlyExp}" . (isset($propertyMetadataType->subType) ? ", subType : '{$propertyMetadataType->subType}'}" : "}");
    }

    private function getImportExpForType($args, &$importedItems)
    {
        $type = $args->type;
        $typeClassName = $args->typeClassName;
        $typesPath = $args->typesPath;
        $enumPath = $args->enumPath;

        $result = null;
        switch ($type) {
            case KalturaServerTypes::EnumOfInt:
            case KalturaServerTypes::EnumOfString:
            if (in_array($typeClassName,$importedItems) === false) {
                $importedItems[] = $typeClassName;
                $fileName = $this->utils->toLispCase($typeClassName);
                $result = "import { {$typeClassName} } from '{$enumPath}{$fileName}';";
            }
            break;
            case KalturaServerTypes::Object:
            case KalturaServerTypes::ArrayOfObjects:
                if (!in_array($typeClassName,$importedItems)) {
                    $importedItems[] = $typeClassName;

                    if ($typeClassName === 'KalturaObjectBase')
                    {
                        $typesPath = "../";
                    }
                    $fileName = $this->utils->toLispCase($typeClassName);
                    $result = "import { {$typeClassName} } from '{$typesPath}{$fileName}';";
                }
                break;
            default:
                break;
        }

        return $result;
    }

    protected function toApplicationType($type, $typeClassName)
    {
        $result = new stdClass();
        $result->type = null;
        $result->subType = null;

        switch ($type) {
            case KalturaServerTypes::File:
                $result->type = 'f';
                break;
            case KalturaServerTypes::Simple:
                switch ($typeClassName) {
                    case "constant":
                        $result->type = "c";
                        break;
                    case "bool":
                        $result->type = "b";
                        break;
                    case "bigint":
                    case "float":
                    case "int":
                        $result->type = 'n';
                        break;
                    case "string":
                        $result->type = 's';
                        break;
                    default:
                        throw new Exception("toApplicationType: Unknown simple type {$typeClassName}");
                }
                break;
            case KalturaServerTypes::ArrayOfObjects:
                $result->type = "a";
                $result->subType = $typeClassName;
                break;
            case KalturaServerTypes::EnumOfInt:
                $result->type = "en";
                $result->subType = $typeClassName;
                break;
            case KalturaServerTypes::EnumOfString:
                $result->type = "es";
                $result->subType = $typeClassName;
                break;
            case KalturaServerTypes::Object:
                $result->type = "o";
                $result->subType = $typeClassName;
                break;
            case KalturaServerTypes::Date:
                $result->type = "d";
                break;
            case KalturaServerTypes::Void:
                $result->type = "v";
                break;
            default:
                throw new Exception("toApplicationType: Unknown type requested {$type}");
        }

        return $result;
    }

    private function hasFileProperty($array)
    {
        $searchedValue = KalturaServerTypes::File;
        $neededObject = array_filter(
            $array,
            function ($e) use (&$searchedValue) {
                return $e->type === $searchedValue;
            }
        );

        return $neededObject !== null && !empty($neededObject);
    }

    private function hasRequiredProperty($array)
    {
        $neededObject = array_filter(
            $array,
            function ($e)  {
                return !$e->optional;
            }
        );

        return $neededObject !== null && !empty($neededObject);
    }

    protected function toNG2TypeExp($type, $typeClassName, $resultCreatedCallback = null)
    {
        return parent::toNG2TypeExp($type,$typeClassName,function($type,$typeClassName,$result)
        {
            return $result;
        });
    }
}


