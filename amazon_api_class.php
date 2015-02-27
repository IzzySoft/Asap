<?php
/*
    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.
*/

/** Class to access Amazons Product Advertising API
 * @package Amazon
 * @class AmazonProductAPI
 * @author Sameer Borate
 * @author Andreas Itzchak Rehberg
 * @see <a href='http://www.codediesel.com/' title='CodeDiesel homepage'>CodeDiesel.COM</a>
 * @see <a href='http://www.codediesel.com/php/accessing-amazon-product-advertising-api-in-php/' title='Accessing Amazon Product Advertizing API in PHP'>CodeDiesel Blog Article on this API</a>
 * @see <a href='http://wern-ancheta.com/blog/2013/02/10/getting-started-with-amazon-product-advertising-api/' title='Getting started with Amazon Product Advertizing API'>Wern-Ancheta Blog</a>
 * @version 1.0 by Sameer Borate
 * @version 1.1 by Andreas Itzchak Rehberg: Added constructor and introduced local_site attribute (rawly following <a href='http://wern-ancheta.com/blog/2013/02/10/getting-started-with-amazon-product-advertising-api/' title='Getting started with Amazon Product Advertizing API'>Wern-Ancheta Blog</a>)
 * @brief Class to access Amazons Product Advertising API. Not all possible search/lookup requests are implemented here. You can easily implement the others from the ones given below.
 * @log 2011-10-19 last changes by CodeDiesel
 * @log 2014-04-21 reformatted, ApiDoc added (Izzy)
 * @log 2014-04-22 added constructor (Izzy)
 */

require_once 'amazon_aws_signed_request.php';

class AmazonProductAPI {
    /** Amazon Access Key Id
     * @class AmazonProductAPI
     * @attribute private string public_key
     */
    private $public_key     = "YOUR AMAZON KEY";

    /** Your Amazon Secret Access Key
     * @class AmazonProductAPI
     * @attribute private string private_key
     */
    private $private_key    = "YOUR AMAZON SECRET KEY";

    /** Your Amazon Associate Tag
     *  Now required, effective from 25th Oct. 2011
     * @class AmazonProductAPI
     * @attribute private string associate_tag
     */
    private $associate_tag  = "YOUR AMAZON ASSOCIATE TAG";

    /** Amazon site to query
     * @class AmazonProductAPI
     * @attribute private string local_site
     */
    private $local_site = 'de';

    /* Constants for product types
     * @class AmazonProductAPI
     * @constant string MUSIC
     * @constant string DVD
     * @constant string GAMES
     * @brief Only three categories are listed here. More categories can be found here:
     * @link http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/APPNDX_SearchIndexValues.html
     */
    const MUSIC = "Music";
    const DVD   = "DVD";
    const GAMES = "VideoGames";

    /** Initialize the class with your credentials
     * @constructor AmazonProductAPI
     * @param string public Public key to be used
     * @param string private Private string to be used
     * @param string associate_tag Amazon Partner ID
     * @param optional string local_site Amazon site to query (e.g. 'com','de', …), defaults to 'de'
     */
    public function __construct($public, $private, $associate_tag, $local_site='de') {
      $this->public_key = $public;
      $this->private_key = $private;
      $this->associate_tag = $associate_tag;
      $this->local_site = $local_site;
    }

    /** Check if the xml received from Amazon is valid
     * @class AmazonProductAPI
     * @method verifyXmlResponse
     * @param mixed $response xml response to check
     * @return bool false if the xml is invalid
     * @return mixed the xml response if it is valid
     * @return exception if we could not connect to Amazon
     */
    private function verifyXmlResponse($response) {
        if ($response === False) {
            throw new Exception("Could not connect to Amazon");
        } else {
            if (isset($response->Items->Item->ItemAttributes->Title)) {
                return ($response);
            } else {
                if ( isset($response->Items->Request->Errors->Error) ) {
                  $msg = 'Code: ' . (string) $response->Items->Request->Errors->Error->Code
                       . '; Message: ' . (string) $response->Items->Request->Errors->Error->Message;
                  throw new Exception("Invalid xml response. ${msg}");
                } else {
                  throw new Exception("Invalid xml response.");
                }
            }
        }
    }


    /** Query Amazon with the issued parameters
     * @class AmazonProductAPI
     * @method queryAmazon
     * @param array $parameters parameters to query around
     * @return simpleXmlObject xml query response
     */
    private function queryAmazon($parameters) {
        return aws_signed_request($this->local_site, $parameters, $this->public_key, $this->private_key, $this->associate_tag);
    }


    /** Return details of products searched by various types
     * @class AmazonProductAPI
     * @method searchProducts
     * @param string $search search term
     * @param string $category search category
     * @param string $searchType type of search
     * @return mixed simpleXML object
     */
    public function searchProducts($search, $category, $searchType = "UPC") {
        $allowedTypes = array("UPC", "TITLE", "ARTIST", "KEYWORD");
        $allowedCategories = array("Music", "DVD", "VideoGames");

        switch($searchType) {
            case "UPC" :    $parameters = array("Operation"     => "ItemLookup",
                                                "ItemId"        => $search,
                                                "SearchIndex"   => $category,
                                                "IdType"        => "UPC",
                                                "ResponseGroup" => "Medium");
                            break;
            case "TITLE" :  $parameters = array("Operation"     => "ItemSearch",
                                                "Title"         => $search,
                                                "SearchIndex"   => $category,
                                                "ResponseGroup" => "Medium");
                            break;
        }

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }


    /** Return details of a product searched by UPC
     * @class AmazonProductAPI
     * @method getItemByUpc
     * @param int $upc_code UPC code of the product to search
     * @param string $product_type type of the product
     * @return mixed simpleXML object
     */
    public function getItemByUpc($upc_code, $product_type) {
        $parameters = array("Operation"     => "ItemLookup",
                            "ItemId"        => $upc_code,
                            "SearchIndex"   => $product_type,
                            "IdType"        => "UPC",
                            "ResponseGroup" => "Medium");

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }


    /** Return details of a product searched by ASIN
     * @class AmazonProductAPI
     * @method getItemByAsin
     * @param int $asin_code ASIN code of the product to search
     * @param optional string $response_group Response-Group. Defaults to 'Medium'. For alternatives, see http://docs.aws.amazon.com/AWSECommerceService/latest/DG/CHAP_ResponseGroupsList.html
     * @return mixed simpleXML object
     * @version Note that not all response groups are valid for item lookup by ASIN (e.g. list-types are not, most probably), so we restrict them
     * @log 2014-04-22 added parameter to pass the wanted response-group as parameter, added check for valid response-groups (Izzy)
     */
    public function getItemByAsin($asin_code, $response_group='Medium') {
        $allowedGroups = array(
            'Small','Medium','Large',
            'Reviews','EditorialReview','PromotionSummary','OfferSummary','VariationSummary',
            'Offers','OfferFull',
            'Images','ItemAttributes','ItemIds','SalesRank','VariationImages',
            'Variations', // US only
            'RelatedItems','Similarities',
            'Accessories',
            'Tracks', // Music CDs
            'BrowseNodes'
        );
        if ( !in_array($response_group,$allowedGroups) ) $response_group = 'Medium';

        $parameters = array("Operation"     => "ItemLookup",
                            "ItemId"        => $asin_code,
                            "ResponseGroup" => $response_group);

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }


    /** Return details of a product searched by keyword
     *  Search is done in titles and descriptions, max 10 results are returned
     * @class AmazonProductAPI
     * @method getItemByKeyword
     * @param string $keyword keyword to search
     * @param string $product_type type of the product
     * @param optional string $response_group Response-Group. Defaults to 'Small'. For alternatives, see http://docs.aws.amazon.com/AWSECommerceService/latest/DG/CHAP_ResponseGroupsList.html
     * @return mixed simpleXML object
     * @version Note that not all response groups are valid, and also the response might get quite large. Hence for now, they are limited to Small and Medium.
     * @log 2014-04-22 added parameter "Availibility": it makes no sense to promote articles which are not available, so we exclude those (Izzy)
     * @log 2014-04-22 added parameter to pass the wanted response-group; no check here, as that would be a little complex (Izzy)
     */
    public function getItemByKeyword($keyword, $product_type, $response_group='Small') {
        $allowedGroups = array(
          'Small','Medium'
        );
        if ( !in_array($response_group,$allowedGroups) ) $response_group = 'Small';

        $parameters = array("Operation"   => "ItemSearch",
                            "Keywords"    => $keyword,
                            "ResponseGroup" => $response_group,
                            "Availability" => "Available",
                            "SearchIndex" => $product_type);

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }
}

?>
