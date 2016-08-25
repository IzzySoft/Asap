<?php
define('CACHE_DIR','/web/cache/amazon_api');
require_once("amazon_api_class.php"); // located in /web/include now
require_once(__DIR__."/class.simplecache.php");

/** Retrieving advertizements via the Amazon product API
 * @package Amazon
 * @class AmazonAds
 * @author Andreas Itzchak Rehberg (Izzy)
 * @brief This class makes use of the Amazon API by CodeDiesel, (see http://www.codediesel.com/php/accessing-amazon-product-advertising-api-in-php/), including additions done to it by Izzy.
 */
class AmazonAds {

  /** Initialize the class with your credentials
   * @constructor AmazonAds
   * @param string public Public key to be used
   * @param string private Private string to be used
   * @param string associate_tag Amazon Partner ID
   * @param optional string local_site Amazon site to query (e.g. 'com','de', …), defaults to 'de'
   */
  public function __construct($public, $private, $associate_tag, $local_site='de') {
    $this->api   = new AmazonProductAPI($public, $private, $associate_tag, $local_site);
    $this->cache = new SimpleCache();
    if ( isset($_SERVER['HTTPS']) ) $this->ssl = TRUE;
    else $this->ssl = FALSE;
  }

  /** Override auto-SSL setting
   *  By default, the constructor checks the current connection and matches SSL
   *  mode (to update image URLs accordingly). Here you can override this.
   * @class AmazonAds
   * @method setSSL
   * @param boolean useSSL
   */
  public function setSSL($ssl) {
    $this->ssl = (bool) $ssl;
  }

  /** Load from cache if available
   * @class AmazonAds
   * @method getCache
   * @param string cachename Name of the cache object
   * @param ref string content Where to place the content in
   * @return int cacheAge (UnixTimeStamp; 0 if no cache)
   */
  protected function getCache($cachename,&$content) {
    $tmp = '';
    $age = $this->cache->getCacheUnixTime($cachename);
    if ( $age>0 && time() - $age < 86400 ) { // valid to use
      $this->cache->getCache($tmp,$cachename);
      if ( !empty($tmp) ) $content = simplexml_load_string($tmp);
    }
    return $age;
  }

  /** Return details of a product searched by ASIN
   * @class AmazonAds
   * @method getItemByAsin
   * @param int $asin_code ASIN code of the product to search (comma-separated list)
   * @param optional string $response_group Response-Group. Defaults to 'Medium'.
   * @return array SearchResults (or empty array if none): int cachedate (UnixTime), array[0..n] of array of strings title,url,img,price
   */
  public function getItemByAsin($asin, $response_group='Medium') {
    $xml = '';
    $age = $this->getCache($asin,$xml);
    if ( empty($xml) ) { // cache was either invalid or empty
      $tmp = '';
      try {
        $xml = $this->api->getItemByAsin($asin, $response_group);
      } catch(Exception $e) {
        trigger_error( $e->getMessage(), E_USER_NOTICE );
        $xml = '';
      }
      if ( !empty($xml) ) {
        $tmp = $xml->asXML();
        $this->cache->setCache($tmp,$asin);
      }
      $age = time();
    }
    if ( empty($xml) ) return array();

    $cachedate = date('Y-m-d H:i',$age);
    $resItems  = count($xml->Items->Item);
    $items     = array();

    for ($i=0; $i<$resItems; ++$i) {
      if (isset($xml->Items->Item[$i]->ItemAttributes->Title)) $title = (string) $xml->Items->Item[$i]->ItemAttributes->Title; else continue;
      if (isset($xml->Items->Item[$i]->DetailPageURL)) $url = (string) $xml->Items->Item[$i]->DetailPageURL; else continue;
      isset($xml->Items->Item[$i]->SmallImage->URL) ? $img = (string) $xml->Items->Item[$i]->SmallImage->URL : $img = '';
      if ( $this->ssl ) $img = str_replace('http://ecx.images-amazon.com/','https://images-na.ssl-images-amazon.com/',$img);
      isset($xml->Items->Item[$i]->OfferSummary->LowestNewPrice->FormattedPrice) ? $price = (string) $xml->Items->Item[$i]->OfferSummary->LowestNewPrice->FormattedPrice : $price = '';
      $items[] = array(
        'title' => $title,
        'url'   => $url,
        'img'   => $img,
        'price' => $price
      );
    }

    return array(
      'cachedate' => $cachedate,
      'items'     => $items
    );
  }

  /** Remove items which are too similar.
   *  We certainly don't want just a list of harddisks from the same manufacturer in all sizes, for example
   * @class AmazonAds
   * @method removeSimilar
   * @param ref array items [0..n] of array title,url,img,price
   * @param optional float similarity percentage of maximum allowed similarity of the titles. Defaults to 90
   */
  function removeSimilar(&$items,$similarity=90) {
    $ic = count($items); $sim = array(); $pct = 0;
    for ($i=0; $i<$ic; ++$i) {
      for ($k=$i+1; $k<$ic; ++$k) {
        $foo = similar_text($items[$i]['title'], $items[$k]['title'], $pct );
        if ( $pct > $similarity ) { // too similar
          if ( !in_array($i,$sim) ) $sim[] = $k;
        }
      }
    }
    if ( !empty($sim) ) { // we've got some too close matches
      $sim = array_unique($sim);
      foreach( $sim as $s ) {
        unset ($items[$s]);
      }
      $items = array_merge($items);
    }
  }

  /** Return details of a product searched by keyword.
   *  Wrapper to getItemByKeyword() which takes care for multiple SearchIndexes (product_types)
   * @class AmazonAds
   * @method getItemsByKeyword
   * @param string $keyword space separated list of keywords to search
   * @param string $product_type type of the product (comma separated list of SearchIndexes)
   * @param optional int limit How many results do we want? Defaults to 3; set to "0" for all-we-can-get
   * @param optional float similarity percentage of maximum allowed similarity of the titles. Defaults to 90; set to 0 to keep all
   * @return array SearchResults (or empty array if none): int cachedate (UnixTime), array[0..n] of strings title,url,img,price
   */
  public function getItemsByKeyword($keyword, $product_type, $limit=3, $similarity=90) {
    $searchIndexes = explode(',',$product_type);
    $sic = count($searchIndexes);

    // verify input
    if ( empty($keyword) ) {
        trigger_error('getItemsByKeyword: $keyword must not be empty, or we cannot perform a search', E_USER_ERROR);
        return array();
    }
    if ( $sic == 0 ) {
        trigger_error('getItemsByKeyword: $product_type must not be empty, or we cannot perform a search', E_USER_ERROR);
        return array();
    }

    // prepare for keyword-multi-search if requested
    $keywords = array(); $plus = ''; $many = array();
    $tmp = explode(' ',$keyword);
    foreach ( $tmp as $key ) {
      if ( substr($key,0,1)=='+' ) $plus .= substr($key,1).' ';
      else $many[] = $key;
    }
    unset ($tmp); // discard
    if ( empty($plus) ) { // normal searchstring like "word1 word2 word3"
      $keywords[] = $keyword;
    } elseif ( count($many)==1 ) { // just a single word without prefix, like "+word1 +word2 word3"
      $keywords[] = $plus . $many[0];
    } elseif ( empty($many) ) { // all words with prefix, like "+word1 +word2 +word3"
      $keywords[] = trim($plus);
    } else { // we have at least one prefixed and multiple non-prefixed words, e.g. "+word1 word2 word3"
      foreach ( $many as $m ) {
        $keywords[] = $plus . $m;
      }
    }

    // "ordinary" searches need no further processing
    $kc  = count($keywords);
    if ( $sic==1 && $kc==1 ) { // "ordinary search": one index, list of keywords
      return $this->getItemByKeyword($keywords[0], $product_type, $limit);
    }

    // Still here? OK, enter Multi-Search mode:
    $items = array();
    $cachedate = '9999-12-31 23:59';

    // Get ads for each SearchIndex and keyword group separately, then merge results
    for ($i=0; $i<$sic; ++$i) { // walk search indexes
      for ( $k=0; $k<$kc; ++$k ) { // walk keyword combinations
        $tmp = $this->getItemByKeyword($keywords[$k], $searchIndexes[$i], 0); // unlimited results
        if ( is_array($tmp['items']) ) { // no results, nothing to do
          $items = array_merge( $items, $tmp['items'] );
          if ( !empty($tmp['cachedate']) && $tmp['cachedate'] < $cachedate ) $cachedate = $tmp['cachedate'];
        }
      }
    }
    if ($similarity > 0) $this->removeSimilar($items, $similarity);

    if ($limit < 1) { // getAll
      return array(
        'cachedate' => $cachedate,
        'items'     => $items
      );
    }

    // Pick $limit random elements if we have more than requested
    $litems = array();
    if ( $limit < count($items) ) {
      $ids = array_rand($items,$limit);
      if ( is_array($ids) ) {
        foreach ($ids as $id) $litems[] = $items[$id];
      } else {
        $litems[] = $items[$ids];
      }
    }

    return array(
      'cachedate' => $cachedate,
      'items'     => $litems
    );
  }


  /** Return details of a product searched by keyword.
   *  Search is done in titles and descriptions, max 10 results are returned
   * @class AmazonAds
   * @method getItemByKeyword
   * @param string $keyword space separated list of keywords to search
   * @param string $product_type type of the product (only one SearchIndex! If more shall be queried, use getItemsByKeyword() instead)
   * @param optional int limit How many results do we want? Defaults to 3; set to "0" for all-we-can-get
   * @param optional float similarity percentage of maximum allowed similarity of the titles. Defaults to 90; set to 0 to keep all
   * @return array SearchResults (or empty array if none): int cachedate (UnixTime), array[0..n] of strings title,url,img,price
   */
  public function getItemByKeyword($keyword, $product_type, $limit=3, $similarity=90) {
    $keywords = urlencode($keyword);
    $cachename = "${product_type}--${keywords}";
    $xml = ''; $tmp = '';

    $age = $this->getCache($cachename,$xml);
    if ( empty($xml) ) { // cache was either invalid or empty
      try {
        $xml = $this->api->getItemByKeyword($keyword, $product_type, 'Medium');
        $tmp = $xml->asXML();
        $this->cache->setCache($tmp,$cachename); // create or replace cache
        $age = time();
      }
      catch(Exception $e) { // just in case something bad happens, it should not "show up"
        // echo $e->getMessage();
        trigger_error( $e->getMessage(), E_USER_NOTICE );
        $xml = '';
      }
    }
    if ( empty($xml) ) return array();

    $cachedate = date('Y-m-d H:i',$age);
    $resItems  = count($xml->Items->Item);
    $items     = array();

    for ($i=0; $i<$resItems; ++$i) {
      $img = (string) $xml->Items->Item[$i]->SmallImage->URL;
      if ( $this->ssl ) $img = str_replace('http://ecx.images-amazon.com/','https://images-na.ssl-images-amazon.com/',$img);
      $items[] = array(
        'title' => (string) $xml->Items->Item[$i]->ItemAttributes->Title,
        'url'   => (string) $xml->Items->Item[$i]->DetailPageURL,
        'img'   => $img,
        'price' => (string) $xml->Items->Item[$i]->OfferSummary->LowestNewPrice->FormattedPrice
      );
    }

    // remove items which are too similar
    if ($similarity > 0) $this->removeSimilar($items, $similarity);

    if ( $limit < 1 || count($items) <= $limit ) { // getAll
      return array(
        'cachedate' => $cachedate,
        'items'     => $items
      );
    }

    $ids = array_rand($items,$limit);
    $litems = array();
    if ( is_array($ids) ) {
      foreach ($ids as $id) $litems[] = $items[$id];
    } else {
      $litems[] = $items[$ids];
    }

    return array(
      'cachedate' => $cachedate,
      'items'     => $litems
    );
  }

}

?>