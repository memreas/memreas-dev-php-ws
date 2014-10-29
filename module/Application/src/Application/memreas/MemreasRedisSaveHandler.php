<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\memreas;

use Zend\Cache\Storage\ClearExpiredInterface as ClearExpiredCacheStorage;
use Zend\Cache\Storage\StorageInterface as CacheStorage;
use Zend\Session\SaveHandler\SaveHandlerInterface;
/**
 * Cache session save handler
 */
class MemreasRedisSaveHandler implements SaveHandlerInterface
{
    /**
     * Session Save Path
     *
     * @var string
     */
    protected $sessionSavePath;

    /**
     * Session Name
     *
     * @var string
     */
    protected $sessionName;

    /**
     * The cache storage
     * @var CacheStorage
     */
    protected $cacheStorage;

    /**
     * Constructor
     *
     * @param  CacheStorage $cacheStorage
     */
    public function __construct($cacheStorage)
    {
        $this->setCacheStorage($cacheStorage);
    }

    /**
     * Open Session
     *
     * @param string $savePath
     * @param string $name
     * @return bool
     */
    public function open($savePath, $name)
    {
        // @todo figure out if we want to use these
        $this->sessionSavePath = $savePath;
        $this->sessionName     = $name;

        return true;
    }

    /**
     * Close session
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {
          $id = 'SID-'.$id;
          $r = $this->getCacheStorage()->getCache($id);
        return $r;
    }

    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data)
    {
        if(!empty($_SESSION['user']['user_id'])){
        $id = 'SID-'.$id;
//error_log(' write cache id  '.$id .'  -> '.print_r($data,true));
        return $this->getCacheStorage()->setCache($id, $data);
    } 
        
         
        return true;
    }

    /**
     * Destroy session
     *
     * @param string $id
     * @return bool
     */
    public function destroy($id)
    {
error_log('Session->d'.$id );
         $id = 'SID-'.$id;
        return $this->getCacheStorage()->invalidateCache($id);
    }

    /**
     * Garbage Collection
     *
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime)
    {
error_log('Session->$maxlifetime'.$maxlifetime);
        $cache = $this->getCacheStorage();
         
        return true;
    }

    /**
     * Set cache storage
     *
     * @param  CacheStorage $cacheStorage
     * @return Cache
     */
    public function setCacheStorage($cacheStorage)
    {
        $this->cacheStorage = $cacheStorage;
        return $this;
    }

    /**
     * Get cache storage
     *
     * @return CacheStorage
     */
    public function getCacheStorage()
    {
        return $this->cacheStorage;
    }

    
}
