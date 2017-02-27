<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
namespace peatrevo\mylogstorage;

use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\helpers\VarDumper;

use yii\log\Target;

use MicrosoftAzure\Storage\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\ServiceException;
use MicrosoftAzure\Storage\Table\Models\BatchOperations;
use MicrosoftAzure\Storage\Table\Models\Entity;
use MicrosoftAzure\Storage\Table\Models\EdmType;

class AzureFileStorage extends Target
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbTarget object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $accountName;
    public $accountKey;
    /**
     * @var string name of the DB table to store cache content. Defaults to "log".
     */
    public $logTable = '{{%yii2logtable}}';
    /**
     * Initializes the DbTarget component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->connectionString = 'DefaultEndpointsProtocol=https;AccountName=' . $this->accountName . ';AccountKey=' . $this->accountKey;
        $this->tableClient = ServicesBuilder::getInstance()->createTableService($this->connectionString);
        //$this->db = Instance::ensure($this->db, Connection::className());
    }
    /**
     * Stores log messages to DB.
     */
    public function export()
    {
        $tableName = $this->logTable;
        
        $batchOp = new BatchOperations();
        $i = 0;
        foreach ($this->messages as $message) {
            $i++;
            list($text, $level, $category, $timestamp) = $message;

            if (!is_string($text)) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($text instanceof \Throwable || $text instanceof \Exception) {
                    $text = (string) $text;
                } else {
                    $text = VarDumper::export($text);
                }
            }

            $entity = new Entity();
            $entity->setPartitionKey("pk");
            $entity->setRowKey(''.$i);
            $entity->addProperty("level", EdmType::STRING, $level);
            $entity->addProperty("category", EdmType::STRING, $category);
            $entity->addProperty("log_time", EdmType::STRING, $timestamp);
            $entity->addProperty("prefix", EdmType::STRING, $this->getMessagePrefix($message));
            $entity->addProperty("message", EdmType::STRING, $text);
            
            $batchOp->addInsertEntity($tableName, $entity);
        }

        try {
            $this->tableClient->batch($batchOp);
        } catch(ServiceException $e){
            $code = $e->getCode();
            $error_message = $e->getMessage();
            echo $code.": ".$error_message.PHP_EOL;
        }


    }
}