<?php

/** Cache specific stuff
 * @package Api
 * @class SimpleCache
 */

class SimpleCache {

  /** Where cache files should be stored
   * @class SimpleCache
   * @attribute string cachedir
   */
  var $cachedir = '/web/cache/amazon_api';

  /** Initialization (constructor)
   * @constructor SimpleCache
   * @param optional string cachedir Where to place your cache files (directory must exist, and be accessible r/w – or caching will soft-fail)
   */
  function __construct($cachedir='') {
    if ( !empty($cachedir) ) $this->cachedir = $cachedir;
    return;
  }

  /** Evaluate cache file name
   * @class SimpleCache
   * @method getCacheFilename
   * @param string name Name of the cache object (e.g. 'cat_123')
   * @param optional string section Section it belongs into (e.g. 'cat'); will be used as cache subdir if set
   * @return string filename
   */
  function getCacheFilename($name,$section='') {
    $filename = $this->cachedir;
    if ( !empty($section) ) $filename .= DIRECTORY_SEPARATOR . $section;
    if ( !is_dir($filename) ) trigger_error("Warning: path to this cache section does not yet exist ('${filename}' for name='${name}', section='${section}')!",E_USER_NOTICE);
//    if ( preg_match('![^A-z0-9_]!',$name) ) trigger_error("Warning: \$name contains invalid characters (${name})!",E_USER_NOTICE);
    $filename .= DIRECTORY_SEPARATOR . $name . ".gz";
    return $filename;
  }

  /** Write cache
   * @class SimpleCache
   * @method setCache
   * @param string content Content to be put to cache. If empty, corresponding cache file will be removed
   * @param string name Name of the cache object (e.g. 'cat_123')
   * @param optional string section Section it belongs into (e.g. 'cat'); will be used as cache subdir if set
   * @param optional string timestamp Timestamp to apply to cache file (YYYY-MM-DD)
   */
  function setCache(&$content,$name,$section='',$timestamp='') {
    $fname = $this->getCacheFilename($name,$section);
    if ( empty($content) ) { // remove file
      if (is_file($fname)) {
        if ( !unlink($fname) ) trigger_error("Warning: could not delete cache file '${fname}'!",E_USER_NOTICE);
      }
      return;
    }
    if ( is_dir(dirname($fname)) ) {
      $fp = gzopen ($fname, "w");
      gzputs ($fp, $content);
      gzclose ($fp);
      if ( !empty($timestamp) ) {
        touch($fname,strtotime($timestamp));
      }
    } else {
      trigger_error("Warning: directory for '${fname}' does not exist, no cache written.",E_USER_NOTICE);
    }
  }

  /** Remove a cached element
   * @class SimpleCache
   * @method dropCache
   * @param string name Name of the cache object (e.g. 'cat_123')
   * @param optional string section Section it belongs into (e.g. 'cat'); will be used as cache subdir if set
   */
  function dropCache($name,$section='') {
    static $dummy = '';
    $this->setCache($dummy,$name,$section);
  }

  /** Read cache
   * @class SimpleCache
   * @method getCache
   * @param string content String to place the cache contents into (passed by reference)
   * @param string name Name of the cache object (e.g. 'cat_123')
   * @param optional string section Section it belongs into (e.g. 'cat'); will be used as cache subdir if set
   */
  function getCache(&$content,$name,$section='') {
    $fname = $this->getCacheFilename($name,$section);
    if ( is_file($fname) && is_readable($fname) ) { // if we have no cache, $content is simply left as-is
      $content = @join("",@gzfile($fname));
    }
  }

  /** Obtain cache LastMod UnixTimestamp
   * @class SimpleCache
   * @method getCacheUnixTime
   * @param string name Name of the cache object (e.g. 'cat_123')
   * @param optional string section Section it belongs into (e.g. 'cat'); will be used as cache subdir if set
   * @return int UnixTimeStamp of last modification
   */
  function getCacheUnixTime($name,$section='') {
    $fname = $this->getCacheFilename($name,$section);
    if ( is_file($fname) && is_readable($fname) ) {
      return filemtime($fname);
    } else {
      return 0;
    }
  }

}
?>