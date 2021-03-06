<?php

/**
 * This file is part of the PHPMongo package.
 *
 * (c) Dmytro Sokil <dmytro.sokil@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sokil\Mongo;

use \Psr\Log\LoggerInterface;

/**
 * Connection manager and factory to get database and collection instances.
 * 
 * @link https://github.com/sokil/php-mongo#connecting Connecting
 * @link https://github.com/sokil/php-mongo#selecting-database-and-collection Get database and collection instance
 */
class Client
{
    const DEFAULT_DSN = 'mongodb://127.0.0.1';
    
    private $dsn = self::DEFAULT_DSN;
    
    private $connectOptions = array();
    
    /**
     *
     * @var \MongoClient
     */
    private $mongoClient;
    
    private $databasePool = array();
    
    /**
     * @var array Database to class mapping
     */
    protected $_mapping = array();
    
        
    private $logger;
    
    private $currentDatabaseName;

    /**
     *
     * @var string version of MongoDb
     */
    private $dbVersion;
    
    /**
     * 
     * @param string $dsn Data Source Name
     * @param array $options
     */
    public function __construct($dsn = null, array $options = null) {
        if($dsn) {
            $this->setDsn($dsn);
        }
        
        if($options) {
            $this->setConnectOptions($options);
        }
    }
    
    /**
     * Set credentials to auth on db, specified in connect options or dsn.
     * If not specified - auth on admin db
     * 
     * @param type $username
     * @param type $password
     * @return \Sokil\Mongo\Client
     */
    public function setCredentials($username, $password)
    {
        $this->connectOptions['username'] = $username;
        $this->connectOptions['password'] = $password;
        
        return $this;
    }
    
    public function __get($name)
    {
        return $this->getDatabase($name);
    }
    
    /**
     * 
     * @return string Version of PHP driver
     */
    public function getVersion()
    {
        return \MongoClient::VERSION;
    }

    /**
     *
     * @return string verion of mongo database
     */
    public function getDbVersion()
    {
        if ($this->dbVersion) {
            return $this->dbVersion;
        }

        $buildInfo = $this
            ->getDatabase('admin')
            ->executeCommand(array('buildinfo' => 1));
        
        $this->dbVersion = $buildInfo['version'];
        return $this->dbVersion;
    }
    
    public function setDsn($dsn)
    {
        $this->dsn = $dsn;
        return $this;
    }
    
    public function getDsn()
    {
        return $this->dsn;
    }
    
    /**
     * Set connect options
     * 
     * @link http://php.net/manual/en/mongoclient.construct.php connect options
     * @param array $options
     * @return \Sokil\Mongo\Client
     */
    public function setConnectOptions(array $options)
    {
        $this->connectOptions = $options;
        return $this;
    }

    public function getConnectOptions()
    {
        return $this->connectOptions;
    }
    
    /**
     * Set mongo's client
     * 
     * @param \MongoClient $client
     * @return \Sokil\Mongo\Client
     */
    public function setMongoClient(\MongoClient $client)
    {
        $this->mongoClient = $client;
        return $this;
    }
    
    /**
     * Get mongo connection instance
     *
     * @return \MongoClient
     * @throws \Sokil\Mongo\Exception
     */
    public function getMongoClient()
    {
        if($this->mongoClient) {
            return $this->mongoClient;
        }

        $this->mongoClient = new \MongoClient($this->dsn, $this->connectOptions);
        
        return $this->mongoClient;
    }
    
    /**
     * Get list of all active connections through this client
     * 
     * @return type
     */
    public function getConnections()
    {
        return $this->mongoClient->getConnections();
    }
    
    /**
     * Map database and collection name to class
     * 
     * @param array $mapping classpath or class prefix
     * Classpath:
     *  [dbname => [collectionName => collectionClass, ...], ...]
     * Class prefix:
     *  [dbname => classPrefix]
     * 
     * @return \Sokil\Mongo\Client
     */
    public function map(array $mapping) {
        $this->_mapping = $mapping;
        
        return $this;
    }
    
    /**
     * 
     * @param string $name database name
     * @return \Sokil\Mongo\Database
     */
    public function getDatabase($name = null) {
        
        if(!$name) {
            $name = $this->getCurrentDatabaseName();
        }

        if(!isset($this->databasePool[$name])) {
            // init db
            $database = new Database($this, $name);
            if(isset($this->_mapping[$name])) {
                $database->map($this->_mapping[$name]);
            }

            // configure db
            $this->databasePool[$name] = $database;
        }
        
        return $this->databasePool[$name];
    }
    
    /**
     * Select database
     * 
     * @param string $name
     * @return \Sokil\Mongo\Client
     */
    public function useDatabase($name)
    {
        $this->currentDatabaseName = $name;
        return $this;
    }
    
    public function getCurrentDatabaseName()
    {
        if(!$this->currentDatabaseName) {
            throw new Exception('Database not selected');
        }

        return $this->currentDatabaseName;
    }
    
    /**
     * Get collection from previously selected database by self::useDatabase()
     * 
     * @param string $name
     * @return \Sokil\Mongo\Collection
     * @throws Exception
     */
    public function getCollection($name)
    {        
        return $this
            ->getDatabase($this->getCurrentDatabaseName())
            ->getCollection($name);
    }
    
    public function readPrimaryOnly()
    {
        $this->getMongoClient()->setReadPreference(\MongoClient::RP_PRIMARY);
        return $this;
    }
    
    public function readPrimaryPreferred(array $tags = null)
    {
        $this->getMongoClient()->setReadPreference(\MongoClient::RP_PRIMARY_PREFERRED, $tags);
        return $this;
    }
    
    public function readSecondaryOnly(array $tags = null)
    {
        $this->getMongoClient()->setReadPreference(\MongoClient::RP_SECONDARY, $tags);
        return $this;
    }
    
    public function readSecondaryPreferred(array $tags = null)
    {
        $this->getMongoClient()->setReadPreference(\MongoClient::RP_SECONDARY_PREFERRED, $tags);
        return $this;
    }
    
    public function readNearest(array $tags = null)
    {
        $this->getMongoClient()->setReadPreference(\MongoClient::RP_NEAREST, $tags);
        return $this;
    }

    public function getReadPreference()
    {
        return $this->getMongoClient()->getReadPreference();
    }
    
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
    
    /**
     * 
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Check if logger defined
     *
     * @return bool
     */
    public function hasLogger()
    {
        return (bool) $this->logger;
    }

    /**
     * Remove logger
     *
     * @return \Sokil\Mongo\Client
     */
    public function removeLogger()
    {
        $this->logger = null;
        return $this;
    }
    
    /**
     * Define write concern on whole requests
     *
     * @param string|integer $w write concern
     * @param int $timeout timeout in milliseconds
     * @return \Sokil\Mongo\Client
     *
     * @throws \Sokil\Mongo\Exception
     */
    public function setWriteConcern($w, $timeout = 10000)
    {
        if(!$this->getMongoClient()->setWriteConcern($w, (int) $timeout)) {
            throw new Exception('Error setting write concern');
        }
        
        return $this;
    }
    
    /**
     * Define unacknowledged write concern on whole requests
     *
     * @param int $timeout timeout in milliseconds
     * @return \Sokil\Mongo\Client
     */
    public function setUnacknowledgedWriteConcern($timeout = 10000)
    {
        $this->setWriteConcern(0, (int) $timeout);
        return $this;
    }
    
    /**
     * Define majority write concern on whole requests
     *
     * @param int $timeout timeout in milliseconds
     * @return \Sokil\Mongo\Client
     */
    public function setMajorityWriteConcern($timeout = 10000)
    {
        $this->setWriteConcern('majority', (int) $timeout);
        return $this;
    }

    /**
     * Get currently active write concern on connection level
     *
     * @return string|int
     */
    public function getWriteConcern()
    {
        return $this->getMongoClient()->getWriteConcern();
    }

    /**
     * Create new persistence manager
     * @return \Sokil\Mongo\Persistence
     */
    public function createPersistence()
    {
        // operations of same type and in same collection executed at once 
        if (version_compare($this->getVersion(), '1.5', '>=') && version_compare($this->getDbVersion(), '2.6', '>=')) {
            return new Persistence();
        }

        // all operations executed separately
        return new PersistenceLegacy();
    }
}
