<?php
/*
 * Copyright (C) 2015 Jérôme Gasperi <jerome.gasperi@gmail.com>
 *
 * This file is subject to the terms and conditions defined in
 * file 'LICENSE.txt', which is part of this source code package.
 */

/**
 * STAC add-on
 */
class STAC extends RestoAddOn
{

    /**
     * Links
     *
     * @OA\Schema(
     *      schema="Link",
     *      description="Link",
     *      required={"rel", "href"},
     *      @OA\Property(
     *          property="rel",
     *          type="string",
     *          description="Relationship between the feature and the linked document/resource"
     *      ),
     *      @OA\Property(
     *          property="type",
     *          type="string",
     *          description="Mimetype of the resource"
     *      ),
     *      @OA\Property(
     *          property="title",
     *          type="string",
     *          description="Title of the resource"
     *      ),
     *      @OA\Property(
     *          property="href",
     *          type="string",
     *          description="Url to the resource"
     *      ),
     *      example={
     *          "rel": "self",
     *          "type": "application/json",
     *          "href": "http://127.0.0.1:5252/collections/S2.json?&_pretty=1"
     *      }
     * )
     * 
     * Assets
     *
     * @OA\Schema(
     *      schema="Asset",
     *      description="Asset links",
     *      required={"rel", "href"},
     *      @OA\Property(
     *          property="rel",
     *          type="string",
     *          description="Relationship between the feature and the linked document/resource"
     *      ),
     *      @OA\Property(
     *          property="type",
     *          type="string",
     *          description="Mimetype of the resource"
     *      ),
     *      @OA\Property(
     *          property="title",
     *          type="string",
     *          description="Title of the resource"
     *      ),
     *      @OA\Property(
     *          property="href",
     *          type="string",
     *          description="Url to the resource"
     *      ),
     *      @OA\Property(
     *          property="roles",
     *          type="array",
     *          description="Asset roles",
     *          @OA\Items(
     *              type="string",
     *          )
     *      ),
     *      example={
     *          "href": "https://landsat-pds.s3.amazonaws.com/c1/L8/171/002/LC08_L1TP_171002_20200616_20200616_01_RT/LC08_L1TP_171002_20200616_20200616_01_RT_B1.TIF",
     *          "type": "image/tiff; application=geotiff; profile=cloud-optimized",
     *          "roles":{"data"},
     *          "eo:bands": {
     *              0
     *          }
     *      }
     * )
     */

    /*
     * STAC version
     */
    const STAC_VERSION = '1.0.0';

    /**
     * Add-on version
     */
    public $version = '1.0.4';

    /**
     * Constructor
     *
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user)
    {
        parent::__construct($context, $user);
    }

    /**
     * Get root links
     */
    public function getRootLinks()
    {
        $links = array(
            array(
                'rel' => 'root',
                'type' => RestoUtil::$contentTypes['json'],
                'title' => getenv('API_INFO_TITLE'),
                'href' => $this->context->core['baseUrl']
            ),
            array(
                'rel' => 'search',
                'type' => RestoUtil::$contentTypes['json'],
                'title' => 'STAC search endpoint',
                'href' => $this->context->core['baseUrl'] . '/search'
            )
        );

        /*
         * [TODO] Remove - resto already returns collections from rel="data" 
         * Return all non-empty collections
         *
        $collections = ((new RestoCollections($this->context, $this->user))->load())->toArray();
        $links[] = array(
            'rel' => 'child',
            'title' => 'Collections',
            'type' => RestoUtil::$contentTypes['json'],
            'matched' =>  count($collections['collections']),
            'href' => $this->context->core['baseUrl'] . '/collections',
            'roles' => array('collections')
        );
        */

        /*
         * Get additional catalogs
         */
        if (isset($this->context->addons['STACPlus'])) {
            $stacCatalog = (new STACPlus($this->context, $this->user))->load();
            return array_merge($links, $stacCatalog->links);
        }

        return $links;

    }

    /**
     * Search for features in all collections
     *
     *  @OA\Get(
     *      path="/search",
     *      summary="STAC search endpoint",
     *      description="List of filters to search features within all collections",
     *      tags={"Feature"},
     *      @OA\Parameter(
     *          name="model",
     *          in="query",
     *          description="Search features within collections belonging to *model* - e.g. *model=SatelliteModel* will search in all satellite collections",
     *          required=false,
     *          style="form",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="collections",
     *          in="query",
     *          style="form",
     *          description="Search features within collections - comma separated list of collection identifiers",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *         name="ck",
     *         in="query",
     *         style="form",
     *         required=false,
     *         description="Stands for *collection keyword* - limit results to collection containing the input keyword",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *          name="q",
     *          in="query",
     *          style="form",
     *          description="Free text search - OpenSearch {searchTerms}. Can include hashtags i.e. text starting with *#* characters. In this case, use the following:
* *#cryosphere* will search for *cryosphere*
* *#cryosphere #atmosphere* will search for *cryosphere* AND *atmosphere*
* *#cryosphere|atmosphere* will search for *cryosphere* OR *atmosphere*
* *#cryosphere!* will search for *cryosphere* OR any *broader* concept of *cryosphere* ([EXTENSION][SKOS])
* *#cryosphere\** will search for *cryosphere* OR any *narrower* concept of *cryosphere* ([EXTENSION][SKOS])
* *#cryosphere~* will search for *cryosphere* OR any *related* concept of *cryosphere* ([EXTENSION][SKOS])",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          style="form",
     *          description="Number of results returned per page - between 1 and 500 (default 20) - OpenSearch {count}",
     *          required=false,
     *          @OA\Schema(
     *              type="integer",
     *              minimum=1,
     *              maximum=500,
     *              default=20
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="startIndex",
     *          in="query",
     *          style="form",
     *          description="First result to provide - minimum 1, (default 1) - OpenSearch {startIndex}",
     *          required=false,
     *          @OA\Schema(
     *              type="integer",
     *              minimum=1,
     *              default=1
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          style="form",
     *          description="First page to provide - minimum 1, (default 1) - OpenSearch {startPage}",
     *          required=false,
     *          @OA\Schema(
     *              type="integer",
     *              minimum=1,
     *              default=1
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="lang",
     *          in="query",
     *          style="form",
     *          description="Two letters language code according to ISO 639-1 (default *en*) - OpenSearch {language}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="ids",
     *          in="query",
     *          style="form",
     *          description="Array of item ids to return. All other filter parameters that further restrict the number of search results (except next and limit) are ignored",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="intersects",
     *          in="query",
     *          style="form",
     *          description="Region of Interest defined in GeoJSON or in Well Known Text standard (WKT) with coordinates in decimal degrees (EPSG:4326) - OpenSearch {geo:geometry}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="bbox",
     *          in="query",
     *          style="form",
     *          description="Region of Interest defined by 'west, south, east, north' coordinates of longitude, latitude, in decimal degrees (EPSG:4326) - OpenSearch {geo:box}",
     *          required=false,
     *          @OA\Schema(
     *              type="array",
     *              minItems=4,
     *              maxItems=6,
     *              @OA\Items(
     *                  type="number",
     *              )
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          in="query",
     *          style="form",
     *          description="[EXTENSION][egg] Location string e.g. Paris, France  or toponym identifier (i.e. geouid:xxxx) - OpenSearch {geo:name}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="lon",
     *          in="query",
     *          style="form",
     *          description="Longitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lat - OpenSearch {geo:lon}",
     *          required=false,
     *          @OA\Schema(
     *              type="number"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="lat",
     *          in="query",
     *          style="form",
     *          description="Latitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lon - OpenSearch {geo:lat}",
     *          required=false,
     *          @OA\Schema(
     *              type="number"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="radius",
     *          in="query",
     *          style="form",
     *          description="Radius expressed in meters - should be used with geo:lon and geo:lat - OpenSearch {geo:radius}",
     *          required=false,
     *          @OA\Schema(
     *              type="number"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="datetime",
     *          in="query",
     *          style="form",
     *          description="Single date+time, or a range ('/' separator) of the search query. Format should follow RFC-3339 - OpenSearch {time:start}/{time:end}",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *              pattern="^([0-9]{4})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])[Tt]([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9]|60)([\.,][0-9]+)?(([Zz])|([\+|\-]([01][0-9]|2[0-3]):[0-5][0-9]))$"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="start",
     *          in="query",
     *          style="form",
     *          description="Beginning of the time slice of the search query. Format should follow RFC-3339 - OpenSearch {time:start}",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *              pattern="^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="end",
     *          in="query",
     *          style="form",
     *          description="End of the time slice of the search query. Format should follow RFC-3339 - OpenSearch {time:end}",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *              pattern="^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="created",
     *          in="query",
     *          style="form",
     *          description="Returns products with metadata creation date greater or equal than *created* - OpenSearch {dc:date}",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="date-time",
     *              pattern="^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="prev",
     *          in="query",
     *          style="form",
     *          description="Returns features with *sort* key value greater than *prev* value - use this for pagination. The value is a unique iterator computed from the *sort* key value and provided within each feature properties as *sort_idx* property",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="next",
     *          in="query",
     *          style="form",
     *          description="Returns features with *sort* key value lower than *next* value - use this for pagination. The value is a unique iterator computed from the *sort* key value and provided within each feature properties as *sort_idx* property",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="pid",
     *          in="query",
     *          style="form",
     *          description="Like on product identifier",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sort",
     *          in="query",
     *          style="form",
     *          description="Sort results by property *startDate* or *created* (default *startDate*). Sorting order is DESCENDING (ASCENDING if property is prefixed by minus sign)",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="owner",
     *          in="query",
     *          style="form",
     *          description="Limit search to owner's features",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="likes",
     *          in="query",
     *          style="form",
     *          description="[EXTENSION][social] Limit search to number of likes (interval)",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="liked",
     *          in="query",
     *          style="form",
     *          description="[EXTENSION][social] Return only liked features from calling user",
     *          required=false,
     *          @OA\Schema(
     *              type="boolean"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          in="query",
     *          style="form",
     *          description="Feature status (unusued)",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="productType",
     *          in="query",
     *          style="form",
     *          description="[MODEL][SatelliteModel] A string identifying the entry type (e.g. ER02_SAR_IM__0P, MER_RR__1P, SM_SLC__1S, GES_DISC_AIRH3STD_V005) - OpenSearch {eo:productType}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="processingLevel",
     *          in="query",
     *          style="form",
     *          description="[MODEL][SatelliteModel] A string identifying the processing level applied to the entry - OpenSearch {eo:processingLevel}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="platform",
     *          in="query",
     *          style="form",
     *          description="[MODEL][SatelliteModel] A string with the platform short name (e.g. Sentinel-1) - OpenSearch {eo:platform}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="instrument",
     *          in="query",
     *          style="form",
     *          description="[MODEL][SatelliteModel] A string identifying the instrument (e.g. MERIS, AATSR, ASAR, HRVIR. SAR) - OpenSearch {eo:instrument}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sensorType",
     *          in="query",
     *          style="form",
     *          description="[MODEL][SatelliteModel] A string identifying the sensor type. Suggested values are: OPTICAL, RADAR, ALTIMETRIC, ATMOSPHERIC, LIMB - OpenSearch {eo:sensorType}",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="cloudCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][OpticalModel] Cloud cover expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="snowCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][OpticalModel] Snow cover expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="waterCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Water area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="urbanCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Urban area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="iceCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Ice area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="herbaceousCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Herbaceous area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="forestCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Forest area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="floodedCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Flooded area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="desertCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Desert area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="cultivatedCover",
     *          in="query",
     *          style="form",
     *          description="[MODEL][LandCoverModel] Cultivated area expressed in percent",
     *          required=false,
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="fields",
     *          in="query",
     *          style="form",
     *          description="Comma separated list of property fields to be returned",
     *          required=false,
     *          @OA\Items(
     *              type="string"
     *          ),
     *          description="Comma separated list of property fields to be returned. The following reserved keywords can also be used:
* _all: Return all properties (This is the default)
* _simple: Return all fields except *keywords* property"
     *      ),
     *      @OA\Parameter(
     *          name="_heatmapNoGeo",
     *          in="query",
     *          style="form",
     *          description="[EXTENSION][Heatmap] True to compute search result heatmap without taking account geographical filter",
     *          required=false,
     *          @OA\Schema(
     *              type="boolean"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Features collection",
     *          @OA\JsonContent(ref="#/components/schemas/RestoFeatureCollection")
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Bad request (i.e. invalid parameter)",
     *          @OA\JsonContent(ref="#/components/schemas/BadRequestError")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Collection not Found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *      )
     * )
     *
     * @param array params
     */
    public function search($params, $body)
    {

        if ($this->context->method === 'POST') {
            $params = $this->jsonQueryToKVP($body);
        }
        
        $model = null;
        if (isset($params['model'])) {
            if (! class_exists($params['model'])) {
                return RestoLogUtil::httpError(400, 'Unknown model ' . $params['model']);
            }
            $model = new $params['model']();
        }
        
        // [STAC] Only one of either intersects or bbox should be specified. If both are specified, a 400 Bad Request response should be returned.
        if (isset($params['intersects']) && isset($params['bbox'])) {
            return RestoLogUtil::httpError(400, 'Only one of either intersects or bbox should be specified');
        }

        /*
         * [TODO][CHANGE THIS] Temporary solution for collection that are not in resto schema 
         *   => replace search on single collection by direct search on single collection
         */
        if ( isset($params['collections']) )
        {
            $collections = array_map('trim', explode(',', $params['collections']));
            if  ( count($collections) === 1 ) {
                $params['collectionId'] = $params['collections'];
                unset($params['collections']);
                return (new RestoCollection($params['collectionId'], $this->context, $this->user))->load()->search($params);
            }
        }

        $restoCollections = (new RestoCollections($this->context, $this->user))->load($params);

        /* [TODO] Faire un UNION sur les collections
        if ( !isset($model) ) {
            $schemaNames = array();
            foreach (array_keys($restoCollections->collections) as $collectionId) {
                if ( !in_array($restoCollections->collections[$collectionId]->model->dbParams['tablePrefix'], $schemaNames) ) {
                    $schemaNames[] = $restoCollections->collections[$collectionId]-model->dbParams['tablePrefix'];
                }   
            }
        }
        */

        // Set Content-Type to GeoJSON
        if ($this->context->outputFormat === 'json') {
            $this->context->outputFormat = 'geojson';
        }
        
        return $restoCollections->search($model, $params);
    }

    /**
     * Return an asset href within an HTTP 301 Redirect message
     * This trick is used to store download external asset statistics
     * 
     * @param array $params
     */
    public function getAsset($params) {

        $url = base64_decode($params['urlInBase64']);

        /*
         * Should be a valid url
         */
        if ( !$url || strpos($url, 'http') !== 0 )  {
            RestoLogUtil::httpError(400, 'Invalid base64 encoded url');
        }

        /*
         * Store download in logs
         */
        try {
            (new GeneralFunctions($this->context->dbDriver))->storeQuery($this->user && $this->user->profile ? $this->user->profile['id'] : null, array(
                'path' => $url,
                'method' => 'GET_ASSET'
            ));
        } catch (Exception $e) { 
            error_log('[WARNING] Cannot store download info in resto.log');
        }

        /*
         * Permanent 301 redirection
         */
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $url);

        return;

    }

    /**
     * Convert JSON query to queryParam
     * 
     * @param array $jsonQuery
     */
    private function jsonQueryToKVP($jsonQuery) {
        return array(
            'query' => 'TODO'
        );
    }

}
