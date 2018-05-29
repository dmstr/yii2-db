<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2018 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\console\controllers;

use InvalidArgumentException;
use SimpleXMLElement;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\ActiveRecord;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;
use yii\helpers\StringHelper;

/**
 * Command to export models to either an json or xml file and import them back into the db
 *
 * @package project\commands
 * @author Elias Luhr <e.luhr@herzogkommunikation.de>
 *
 * @property string type Either `json` or `xml`
 * @property string tablePrefix
 * @property array allowedTypes
 */
class ModelController extends Controller
{

    const TYPE_JSON = 'json';
    const TYPE_XML = 'xml';

    public $type = self::TYPE_JSON;
    public $tablePrefix = '';

    /**
     * @param \yii\base\Action $action
     * @return bool|int
     * @throws InvalidConfigException
     */
    public function beforeAction($action)
    {
        if (!\in_array($this->type, [self::TYPE_JSON, self::TYPE_XML], true)) {
            throw new InvalidConfigException("Type {$this->type} is not supported\n");
        }
        return parent::beforeAction($action);
    }

    /**
     * @param string $actionId
     * @return array|string[]
     */
    public function options($actionId)
    {
        $options = parent::options($actionId);
        $options[] = 'type';
        $options[] = 'tablePrefix';
        return $options;
    }

    /**
     * Exports model by class name to a defined location as a file
     *
     * EXAMPLE USUAGE:
     *
     * Basic as JSON (default)
     * >_ yii model/export namespace\\models\\Model --tablePrefix=app_
     *
     * Export as XML
     * >_ yii model/export namespace\\models\\Model --tablePrefix=app_ --type=xml
     * 
     * Export as XML with relations -> On default it does not export relations
     * >_ yii model/export namespace\\models\\Model 1 --tablePrefix=app_ --type=xml
     *
     * Export multiple models at once
     * >_ yii models/export namespace\\models\\Model,namespace\\models\\Model2,... --tablePrefix=app_
     * @param string[]|string $classNames Full namespace of model or list of comma seperated model namespaces
     * @param bool $withRelations Add model relations to output if set to 1
     * @param string $outputPath Output path of generated file. Aliases can be used
     * @return int Exit code
     */
    public function actionExport($classNames, $withRelations = false, $outputPath = '@runtime/output')
    {
        foreach (explode(',', $classNames) as $className) {
            if (!class_exists($className) && !$className instanceof ActiveRecord) {
                $this->stderr("Class '{$className}' does not exist or is not an instance of yii\db\ActiveRecord\n", Console::FG_RED);
                return ExitCode::UNAVAILABLE;
            }
            // get all model data. Bypass any filters by adding where 1=1
            $modelQuery = $className::find()->where('1=1');

            if ((bool)$withRelations === true) {
                try {
                    $models = $modelQuery->with($this->modelRelations($className))->all();
                } catch (\Exception $exception) {
                    $this->stderr("Failed query relations. Check table prefix. Exception says: {$exception->getMessage()}\n", Console::FG_RED);
                    return ExitCode::DATAERR;
                }
            } else {
                $models = $modelQuery->all();

            }
            // check if there is any data and ask user if he wants to continue
            if (empty($models) && $this->confirm("There are no entries for this model. Export anyways?\n", true) === false) {
                return ExitCode::UNAVAILABLE;
            }
            $data = [];
            if ((bool)$withRelations === true) {
                $modelRelations = $this->modelRelations($className);
                /** @var ActiveRecord $model */
                foreach ($models as $model) {
                    foreach ($modelRelations as $modelRelation) {
                        $relationModels = $model->$modelRelation;
                        // get key if array than n:m else 1:n relation between models
                        $key = get_class(is_array($relationModels) ? $relationModels[0] : $relationModels);
                        // add to list if currently not exist in list of data
                        if (!isset($data[$key]) || !in_array($relationModels, $data[$key], true)) {
                            $data[$key][] = $relationModels;
                        }
                    }

                }
            }
            $data[$className] = $models;

            try {
                $fileContent = Json::encode($data);
            } catch (InvalidArgumentException $exception) {
                $this->stderr("Failed while encoding JSON: {$exception->getMessage()}\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if ($this->type === self::TYPE_XML) {
                $xmlElement = new SimpleXMLElement('<root/>');

                // normalize data for xml
                $xmlReadyModels = [];
                foreach (Json::decode($fileContent) as $modelClass => $modelConfigs) {
                    foreach ($modelConfigs as $modelConfig) {
                        $modelConfig['modelClassNamespace'] = $modelClass;
                        $xmlReadyModels[] = $modelConfig;
                    }
                }

                $this->arrayToXml($xmlReadyModels, $xmlElement);


                $fileContent = $xmlElement->asXML();
            }

            // disable exection throwing by setting second attribute to false
            $filePath = \Yii::getAlias($outputPath, false);
            if ($filePath === false) {
                $this->stderr("Alias does not exist\n", Console::FG_RED);
                return ExitCode::IOERR;
            }
            try {
                // make shure that directory exists and can be created
                if (FileHelper::createDirectory($filePath) === false) {
                    $this->stderr("Cannot create create directory in path: '{$filePath}'\n", Console::FG_RED);
                    return ExitCode::DATAERR;
                }
            } catch (Exception $exception) {
                $this->stderr("Error while creating directory for output file '{$filePath}': {$exception->getMessage()}\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
            // rtrim to remove potential slashes and lower filename for consitent names
            $fileName = rtrim($filePath) . '/' . mb_strtolower(StringHelper::basename($className)) . '.' . $this->type;
            if (file_put_contents($fileName, $fileContent) === false) {
                $this->stderr("Error while writing to file {$fileName}\n", Console::FG_RED);
                return ExitCode::IOERR;
            }
            $this->stdout("Exported model data to file {$fileName}\n", Console::FG_GREEN);
        }
        return ExitCode::OK;
    }

    /**
     * Imports given file, convert data into models and saves data in db
     *
     * EXAMPLE USUAGE:
     *
     * Basic import
     * >_ yii model/import /path/to/output/model.json
     *
     * Import by alias
     * >_ yii model/import @path/to/output/model.json
     *
     * Import multiple files (you can even mix them)
     * >_ yii model/import @path/to/output/model.json,/path/to/output/model.xml
     *
     * @param string[]|string $filePaths File path or comma seperated list of file paths to the file(s) to be imported
     * @return int Exit code
     */
    public function actionImport($filePaths)
    {
        foreach (explode(',', $filePaths) as $filePath) {
            $type = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!in_array($type, [self::TYPE_JSON, self::TYPE_XML], true)) {
                $this->stderr("Filetype cannot be '{$type}'\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
            // disable exection throwing by setting second attribute to false
            $importFilePath = \Yii::getAlias($filePath, false);
            if ($importFilePath === false) {
                $this->stderr("Alias does not exist\n", Console::FG_RED);
                return ExitCode::IOERR;
            }
            if (!is_file($importFilePath)) {
                $this->stderr("File path '{$importFilePath}' does not exist\n", Console::FG_RED);
                return ExitCode::IOERR;
            }
            $fileContent = file_get_contents($importFilePath);
            if ($fileContent === false) {
                $this->stderr("Error while getting content from file {$importFilePath}\n", Console::FG_RED);
                return ExitCode::IOERR;
            }
            if ($type === self::TYPE_JSON) {
                try {
                    $importData = Json::decode($fileContent);
                } catch (InvalidArgumentException $exception) {
                    $this->stderr("Failed while decoding JSON: {$exception->getMessage()}\n", Console::FG_RED);
                    return ExitCode::DATAERR;
                }
            } else {
                $xml = simplexml_load_string($fileContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                // not use Yii JSON encode or decode. This methods cannot handle this ;)
                $json = json_encode($xml);
                // cast to array becaus json_decode can return null
                $importData = (array)json_decode($json, true);
            }
            if (empty($importData)) {
                $this->stdout("Nothing to import\n", Console::FG_BLUE);
                return ExitCode::OK;
            }
            foreach ($importData as $className => $modelConfigs) {
                if ($type === self::TYPE_JSON) {
                    if (!class_exists($className) && !$className instanceof ActiveRecord) {
                        $this->stderr("Class '{$className}' does not exist or is not an instance of yii\db\ActiveRecord\n", Console::FG_RED);
                        return ExitCode::UNAVAILABLE;
                    }
                    $savedModels = 0;
                    foreach ($modelConfigs as $modelConfig) {

                        /** @var ActiveRecord $model */
                        $model = new $className($modelConfig);
                        try {
                            if (!$model->save() && $this->confirm("Some errors appeared while adding model. Continue anyways?\n", true) === false) {
                                $this->stderr('Error while saving model: ' . print_r($model->getErrors(), true) . "\n", Console::FG_RED);
                                return ExitCode::UNAVAILABLE;
                            }
                            $savedModels++;
                            // exception needed. Else SQL errors will be thrown
                        } catch (Exception $exception) {
                            $this->stderr('Muting error: ' . print_r($exception->getMessage(), true) . "\n", Console::FG_YELLOW);

                        }
                    }
                    $this->stdout('Imported ' . $savedModels . " models of type {$className}\n", Console::FG_GREEN);
                } else {
                    if (!isset($modelConfigs['modelClassNamespace'])) {
                        $this->stderr("Unable to find namespace in config\n", Console::FG_RED);
                        return ExitCode::DATAERR;
                    }
                    $className = $modelConfigs['modelClassNamespace'];
                    unset($modelConfigs['modelClassNamespace']);
                    $model = new $className($modelConfigs);
                    try {
                        if (!$model->save() && $this->confirm("Some errors appeared while adding model. Continue anyways?\n", true) === false) {
                            $this->stderr('Error while saving model: ' . print_r($model->getErrors(), true) . "\n", Console::FG_RED);
                            return ExitCode::UNAVAILABLE;
                        }
                        // exception needed. Else SQL errors will be thrown
                    } catch (Exception $exception) {
                        $this->stderr('Muting error: ' . print_r($exception->getMessage(), true) . "\n", Console::FG_YELLOW);

                    }
                    $this->stdout("Imported models of type {$className}\n", Console::FG_GREEN);
                }

            }
        }
        return ExitCode::OK;
    }

    /**
     * @param string $className Class name of model
     * @return array Array of model relation names
     *
     * Returns an array model relations
     */
    private function modelRelations($className)
    {
        $relations = [];
        if (is_subclass_of($className, ActiveRecord::class)) {
            /** @var ActiveRecord $className */
            try {
                $tableSchema = $className::getTableSchema();
            } catch (\Exception $exception) {
                return [];
            }
            foreach ($tableSchema->foreignKeys as $key => $value) {
                if (isset($value[0])) {
                    $relations[] = lcfirst(Inflector::id2camel(str_replace($this->tablePrefix, '', $value[0]), '_'));
                }
            }

        }
        return $relations;
    }

    /**
     * @param array $data
     * @param SimpleXMLElement $xml
     */
    private function arrayToXml($data, &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild((string)$key, htmlspecialchars((string)$value));
            }
        }
    }
}