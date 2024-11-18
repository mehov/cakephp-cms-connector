<?php

namespace Bakeoff\CmsConnector;

use Cake\Core\Configure;
use Cake\Http\Exception\InternalErrorException;
use Cake\Utility\Inflector;

/**
 * Represents an individual CMS site. If CMS supports a multisite setup, this is
 * one site within that multisite setup.
 *
 * Extending \Bakeoff\CmsConnector\Plugin, which extends \Cake\Core\BasePlugin,
 * so we can use getName(), getPath() and so on here.
 *
 * @package Bakeoff\CmsConnector
 */
class Site extends Plugin
{

    use \Cake\Core\InstanceConfigTrait;

    /**
     * @var array expected by \Cake\Core\InstanceConfigTrait
     */
    protected $_defaultConfig = [];

    /**
     * @var UPPERCASE key identifying the site configuration being used
     */
    private $_symbol;

    /**
     * @return string
     */
    public function getSymbol()
    {
        return $this->_symbol;
    }

    /**
     * @param string $symbol
     */
    public function setSymbol($symbol)
    {
        $this->_symbol = $symbol;
    }

    /**
     * Constructor.
     *
     * @param null $siteSymbol identifies the site to load from the config
     */
    public function __construct($siteSymbol = null)
    {
        // If none provided, see if a default is configured on the app level
        if (empty($siteSymbol)) {
            $siteSymbol = Configure::read($this->getName().'.defaultSite');
        }
        // If still nothing, throw an exception
        if (empty($siteSymbol)) {
            throw new InternalErrorException('Site symbol not provided');
        }
        // Get the sites list from the config
        $sites = Configure::read($this->getName().'.siteList');
        if (!$sites || !is_array($sites) || empty($sites)) {
            throw new InternalErrorException('No sites configured');
        }
        $siteSymbol = strtoupper($siteSymbol);
        if (!isset($sites[$siteSymbol])) {
            throw new InternalErrorException(sprintf(
                'No site configured for symbol %s. Available symbols: %s',
                $siteSymbol, implode(', ', array_keys($sites))
            ));
        }
        $this->setSymbol($siteSymbol);
        $this->setConfig($sites[$siteSymbol]);
        // Listen to every Model.initialize (filters irrelevant out later)
        \Cake\Event\EventManager::instance()->on('Model.initialize', [$this, 'onEveryModelInitialize']);
    }

    /*
    * Listens to every Model.initialize. Skips models not in this plugin.
    * And every model inside this plugin is prepared.
    *
    * (Prepared means using the right connection and entity class.)
    *
    * @param Cake\Event\Event $event a Model.initialize event
    * @throws \Exception
    */
    /**
     * @param $event
     * @throws \Exception
     */
    public function onEveryModelInitialize($event)
    {
        // Get table class that was just initialised
        $table = $event->getSubject();
        // The registry alias is in Plugin.Model format; split it
        list($plugin, $tableAlias) = pluginSplit($table->getRegistryAlias());
        // Skip right away if this is not a model inside this plugin
        if ($plugin !== $this->getName()) {
            return;
        }
        // Throw exception if no datasource to use has been set yet
        if (empty($this->getConfig('datasource'))) {
            throw new \Exception(
                'No data source was set for '.$this->getName()
            );
        }
        /*
         * Make sure the table uses the right entity class
         */
        // If $table comes from association, getAlias() won't get us real table name
        // So use getTable() to get the original database table name instead
        $tableName = $table->getTable();
        // Convert plural table name to singular entity name
        $entityName = Inflector::classify(Inflector::underscore($tableName));
        // Find the entity location
        $entityLocation = $this->_locateModelClass($entityName, 'Entity');
        // Attempt to set the entity class only if it exists
        if ($entityLocation) {
            $table->setEntityClass($entityLocation);
        }
        // Refer $table to an instance of this site connector
        $table->setConnectedSite($this);
    }

    /**
     * Returns the model table class
     *
     * @param $tableName coming from calls such as $site->Tags (name is 'Tags')
     * @return \Cake\ORM\Table
     */
    public function __get($tableName)
    {
        $tableRegistry = new \Cake\ORM\TableRegistry();
        $tableLocator = $tableRegistry->getTableLocator();
        $tableLocator->clear();// prevent "already exists in the registry" error
        // Make sure table is looked up in blog type folder
        $tableLocator->addLocation('Model/Table/'.$this->getConfig('type'));
        $tableIdentifier = $this->_locateModelClass($tableName, 'Table');
        // If the table class found is located in App itself
        if (strpos($tableIdentifier, '/') !== false) {
            // Drop table class name, keep only location
            $tableLocation = dirname($tableIdentifier);
            // Make locator aware of location where table class was found
            $tableLocator->addLocation('Model/Table/'.$tableLocation);
            // Since we're on App level, look up table by its name only
            $tableIdentifier = $tableName;
        }
        // Get the table
        $table = $tableLocator->get($tableIdentifier, [
            // Always ensure we're using connection configured for this plugin
            'connectionName' => $this->getConfig('datasource'),
            // …otherwise contained table may default to app's connection
        ]);
        if (get_class($table) == 'Cake\ORM\Table') {
            throw new InternalErrorException(sprintf('Requested table %s resolves to generic %s. Make sure a concrete table class exists in %s', $tableName, get_class($table), $this->getPath().'src/Model/Table'));
        }
        return $table;
    }

    /**
     * Tries various class locations for given model (entity or table) name
     *
     * @param string $name model we're looking for; no Table suffix for tables
     * @param string $dir Entity|Table
     * @return string|null CakePHP-compatible class alias in dot notation or not
     */
    private function _locateModelClass($name, $dir)
    {
        // Allow only Entity or Table as $dir values
        if (!in_array($dir, ['Entity', 'Table'])) {
            throw new \Exception(sprintf(
                '$dir needs to be either Entity or Table, received "%s"', $dir
            ));
        }
        // className() looks for exact file names, so add suffix for tables
        $lookup_name = $name . ($dir == 'Table' ? 'Table' : '');
        // If no local path configured, use only entity name, don't look further
        if (empty($this->getConfig('localPath'))) {
            return $name;
        }
        /*
         * From the outside we refer to model files here in this plugin as e.g.:
         * - Bakeoff\Wordpress\Model\Entity\Post
         * - Bakeoff\Wordpress\Model\Table\PostsTable
         * In other words, no mention of version, i.e. if it's Wordpress 5 or 6
         */
        // Build version-agnostic class alias we will be referring to
        $classAlias = sprintf('%s\Model\%s\%s', $this->getName(), $dir, $lookup_name);
        // Internally, the real class path depends on version from the config
        $realClassPath = sprintf('%s\Model\%s\%s\%s', $this->getName(), $dir, $this->getConfig('type'), $lookup_name);
        // Only if the alias has not been declared yet and the real class exists
        if (!\class_exists($classAlias) && \class_exists($realClassPath)) {
            //...link the "external" class alias to the real internal class path
            \class_alias($realClassPath, $classAlias);
            // This sets up version-agnostic alias we look up below
            // Actual version is picked up automatically from the config
        }
        // Local path is where overriding classes *may* be stored in App itself
        $path = trim($this->getConfig('localPath'), '/') . '/' . $lookup_name;
        // See if overriding model class has been created in App itself
        if (\Cake\Core\App::className($path, 'Model/'.$dir)) {
            return $path;
        }
        unset($path);
        // See if the requested model class exists in this plugin
        if (\Cake\Core\App::className($this->getName().'.'.$lookup_name, 'Model/'.$dir)) {
            return $this->getName().'.'.$name;
        }
        // Finally, just have CakePHP look for the model class by its name
        if (\Cake\Core\App::className($lookup_name, 'Model/'.$dir)) {
            return $name;
        }
        return null;
    }

}
