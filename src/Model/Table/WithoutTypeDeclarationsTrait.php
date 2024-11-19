<?php

namespace Bakeoff\CmsConnector\Model\Table;

/**
 * Will be used on older CakePHP versions that do not have type declarations.
 *
 * Custom code that needs to overwrite/be added to \Cake\ORM\Table. Can't simply
 * put it into \Bakeoff\CmsConnector\Model\Table\Table because this has to
 * support both newer and older CakePHP.
 *
 * \Bakeoff\CmsConnector\Model\Table\Table will dynamically pick the trait that
 * matches CakePHP version. The other trait that's supposed to work on newer
 * CakePHP reuses this one anyway, it only adds type declarations where needed.
 *
 * @package Bakeoff\CmsConnector\Model\Table
 */
trait WithoutTypeDeclarationsTrait
{

    /**
     * Refers to connector instance for the site the current table belongs to.
     *
     * Particularly useful for getting configuration for the current site. For
     * example, connection or prefix that this site, and all of its tables, have
     * to use. (This class is parent to all table classes in this plugin.)
     *
     * @var \Bakeoff\CmsConnector\Site
     */
    protected $connectedSite;

    /**
     * @return \Bakeoff\CmsConnector\Site
     */
    public function getConnectedSite()
    {
        return $this->connectedSite;
    }

    /**
     * @param \Bakeoff\CmsConnector\Site $connectedSite
     */
    public function setConnectedSite(\Bakeoff\CmsConnector\Site $connectedSite)
    {
        $this->connectedSite = $connectedSite;
    }

    /**
     * Prepends table name prefix for all table classes in this plugin
     *
     * All table classes in this plugin refer to prefixless database table names
     * Some real CMS databases (especially multisite networks) can use prefixes
     * So, what we neutrally refer to as `posts` needs to really be `wp_3_posts`
     *
     * This function takes over getTable() calls on table classes in this plugin
     *
     * @return string table name with prefix prepended if required
     */
    public function getTable()
    {
        if (!$this->getConnectedSite()) {
            return parent::getTable();
        }
        return $this->getConnectedSite()->getConfig('tablePrefix') . parent::getTable();
    }

    /**
     * Takes over getConnection() calls on all table classes in this plugin and
     * returns datasource connection configured for current connector instance
     *
     * @return \Cake\Database\Connection
     */
    public function getConnection()
    {
        if (!$this->getConnectedSite()) {
            return parent::getConnection();
        }
        $datasource = $this->getConnectedSite()->getConfig('datasource');
        return \Cake\Datasource\ConnectionManager::get($datasource);
    }

}
