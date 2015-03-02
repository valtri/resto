<?php

/*
 * RESTo
 * 
 * RESTo - REstful Semantic search Tool for geOspatial 
 * 
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

/**
 * RESTo PostgreSQL features functions
 */
class Functions_features {
    
    private $dbDriver = null;
    
    private $dbh = null;
    
    /*
     * Non search filters are excluded from search
     */
    private $excludedFilters = array(
        'count',
        'startIndex',
        'startPage',
        'language',
        'geo:name',
        'geo:lat', // linked to geo:lon
        'geo:radius' // linked to geo:lon
    );
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($dbDriver) {
        $this->dbDriver = $dbDriver;
        $this->dbh = $dbDriver->dbh;
    }

    /**
     * 
     * Get an array of features descriptions
     * 
     * @param RestoModel $model
     * @param string $collectionName
     * @param RestoModel $params
     * @param array $options
     *      array(
     *          'limit',
     *          'offset',
     *          'count'// true to return the total number of results without pagination
     * 
     * @return array
     * @throws Exception
     */
    public function search($model, $collectionName, $params, $options) {
        
        /*
         * Check that mandatory filters are set
         */
        $this->checkMandatoryFilters($model, $params);
        
        /*
         * Set search filters
         */
        $filters = $this->prepareFilters($model, $params);
        
        /*
         * TODO - get count from facet statistic and not from count() OVER()
         * 
         * TODO - Add filters depending on user rights
         * $oFilter = superImplode(' AND ', array_merge($filters, $this->getRightsFilters($this->R->getUser()->getRights($this->description['name'], 'get', 'search'))));
         */
        $oFilter = implode(' AND ', $filters);
        
        /*
         * Note that the total number of results (i.e. with no LIMIT constraint)
         * is retrieved with PostgreSQL "count(*) OVER()" technique
         */
        $query = 'SELECT ' . implode(',', $this->getSQLFields($model)) . ($options['count'] ? ', count(' . $model->getDbKey('identifier') . ') OVER() AS totalcount' : '') . ' FROM ' . (isset($collectionName) ? $this->dbDriver->getSchemaName($collectionName) : 'resto') . '.features' . ($oFilter ? ' WHERE ' . $oFilter : '') . ' ORDER BY startdate DESC LIMIT ' . $options['limit'] . ' OFFSET ' . $options['offset'];
     
        /*
         * Retrieve products from database
         */
        $results = $this->dbDriver->query($query);

        /*
         * Loop over results
         */
        $featuresArray = array();
        while ($result = pg_fetch_assoc($results)) {
            $featuresArray[] = $this->correctTypes($model, $result);
        }
        
        return $featuresArray;
    }
    
    /**
     * 
     * Get feature description
     *
     * @param integer $identifier
     * @param RestoModel $model
     * @param string $collectionName
     * @param array $filters
     * 
     * @return array
     * @throws Exception
     */
    public function getFeatureDescription($identifier, $model, $collectionName = null, $filters = array()) {
        $result = $this->dbDriver->query('SELECT ' . implode(',', $this->getSQLFields($model, array('continents', 'countries'))) . ' FROM ' . (isset($collectionName) ? $this->dbDriver->getSchemaName($collectionName) : 'resto') . '.features WHERE ' . $model->getDbKey('identifier') . "='" . pg_escape_string($identifier) . "'" . (count($filters) > 0 ? ' AND ' . join(' AND ', $filters) : ''));
        return $this->correctTypes($model, pg_fetch_assoc($result));
    }
    
    /**
     * Check if feature identified by $identifier exists within {schemaName}.features table
     * 
     * @param string $identifier - feature unique identifier 
     * @param string $schema - schema name
     * @return boolean
     * @throws Exception
     */
    public function featureExists($identifier, $schema = null) {
        $query = 'SELECT 1 FROM ' . (isset($schema) ? pg_escape_string($schema) : 'resto') . '.features WHERE identifier=\'' . pg_escape_string($identifier) . '\'';
        return $this->dbDriver->exists($query);
    }
    
    /**
     * Insert feature within collection
     * 
     * @param array $elements
     * @param RestoModel $model
     * @throws Exception
     */
    public function storeFeature($collectionName, $elements, $model) {
        
        $keys = array(pg_escape_string($model->getDbKey('collection')));
        $values = array('\'' . pg_escape_string($collectionName) . '\'');
        $facets = array(
            array(
                'id' => 'collection:' . $collectionName,
                'hash' => RestoUtil::getHash('collection:' . $collectionName)
            )
        );
        try {
            
            /*
             * Initialize hashes array
             */
            $hashes = array();
                        
            for ($i = count($elements); $i--;) {
                
                /*
                 * Do not process null values
                 */
                if (!isset($elements[$i][1])) {
                    continue;
                }
                
                if (in_array($elements[$i][0], array('updated', 'published', 'collection'))) {
                    continue;
                }
                
                $keys[] = pg_escape_string($model->getDbKey($elements[$i][0]));
                
                /*
                 * Convert geometry to PostgreSQL WKT
                 */
                if ($elements[$i][0] === 'geometry') {
                    $values[] = 'ST_GeomFromText(\'' . RestoGeometryUtil::geoJSONGeometryToWKT($elements[$i][1]) . '\', 4326)';
                }
                
                /*
                 * Special case for keywords
                 * 
                 * It is assumed that $value has the same structure as
                 * the output keywords property i.e. 
                 *   
                 *      $keywords = array(
                 *          array(
                 *              array(
                 *                  "name" => name
                 *                  "id" => id, // type:value
                 *                  "parentId" => id, // parentType:parentValue
                 *                  "hash" => // unique hash for this id
                 *                  "parentHash" => // parent unique hash
                 *                  "value" => value or array()
                 *              ),
                 *              array(
                 *                  ...
                 *              )
                 *          )
                 *      );
                 * 
                 *  keyword are stored in hstore column with the following convention :
                 * 
                 *       hash => urlencode({"name":"...", "hash":"...", "parentHash":"...", "value":"..."})
                 * 
                 *  hash for each keyword is stored within the hases column for search purpose
                 */
                else if ($elements[$i][0] === 'keywords' && is_array($elements[$i][1])) {
                    foreach (array_values($elements[$i][1]) as $keyword) {
                        
                        /*
                         * Compute hash from id if not specified
                         * (this should be the case for input non iTag keywords)
                         */
                        if (isset($keyword['parentId']) && !isset($keyword['parentHash'])) {
                            $keyword['parentHash'] = RestoUtil::getHash($keyword['parentId']);
                        }
                        if (!isset($keyword['hash'])) {
                            $keyword['hash'] = RestoUtil::getHash($keyword['id'], isset($keyword['parentHash']) ? $keyword['parentHash'] : null);
                        }
                        if ($this->dbDriver->facetUtil->getFacetCategory($keyword['id'])) {
                            $facets[] = array(
                                'id' => $keyword['id'],
                                'hash' => $keyword['hash'],
                                'parentId' => isset($keyword['parentId']) ? $keyword['parentId'] : null,
                                'parentHash' => isset($keyword['parentHash']) ? $keyword['parentHash'] : null
                            );
                        }
                       
                        /*
                         * Prepare hstore value as a json string
                         */
                        $json = array(
                            'hash' => $keyword['hash']
                        );
                        foreach (array_keys($keyword) as $property) {
                            if (!in_array($property, array('id', 'parentId', 'hash'))) {
                                $json[$property] = $keyword[$property];
                            }
                        }
                        $quote = count(explode(' ', $keyword['id'])) > 1 ? '"' : '';
                        $propertyTags[] =  $quote . $keyword['id'] . $quote . '=>"' . urlencode(json_encode($json)) . '"';
                        
                        /*
                         * Store both hashes and id to hashes
                         */
                        $hashes[] = '"' . pg_escape_string($keyword['hash']) . '"';
                        
                        
                        $splitted = explode(':', $keyword['id']);
                        if ($splitted[0] !== 'landuse_details') {
                            $hashes[] = '"' . pg_escape_string($keyword['id']) . '"';
                        }
                        
                    }
                    if (isset($propertyTags)) {
                        $values[] = '\'' . pg_escape_string(join(',', $propertyTags)) . '\'';
                    }
                    else {
                        $values[] = '\'\'';
                    }
                    /*
                     * landuse keywords are also stored in dedicated
                     * table columns to speed up search requests
                     */
                    foreach (array_values($elements[$i][1]) as $keyword) {
                        list($facetType, $idId) = explode(':', $keyword['id'], 2);
                        if ($facetType === 'landuse') {
                            $keys[] = 'lu_' . $idId;
                            $values[] = $keyword['value'];
                        }
                    }
                }
                else {
                    
                    /*
                     * Special case for array
                     */
                    if ($model->getDbType($elements[$i][0]) === 'array') {
                        $values[] = '\'{' . pg_escape_string(join(',', $elements[$i][1])) . '}\'';'\'';
                    }
                    else {
                        $values[] = '\'' . pg_escape_string($elements[$i][1]) . '\'';
                    }
                    
                    $id = $elements[$i][0] . ':' . $elements[$i][1];
                    if ($this->dbDriver->facetUtil->getFacetCategory($id)) {
                        
                        /*
                         * Retrieve parent value from input elements
                         */
                        $parentType = $elements[$i][0];
                        $parentIds = array();
                        
                        /*
                         * Compute parentHash from ancestors !
                         */
                        while (isset($parentType)) {
                            $parentType = $this->dbDriver->facetUtil->getFacetParentType($parentType);
                            for ($j = count($elements); $j--;) {
                                if ($elements[$j][0] === $parentType && $elements[$j][1]) {
                                    $parentIds[] = $parentType . ':' . $elements[$j][1];
                                    break;
                                }
                            }
                        }
                        
                        if (count($parentIds) > 0) {
                            $parentHash = null;
                            for ($k = count($parentIds); $k--;) {
                                $parentHash = RestoUtil::getHash($parentIds[$k], $parentHash);
                            }
                            $hash = RestoUtil::getHash($id, $parentHash);
                            $hashes[] = '"' . pg_escape_string($hash) . '"';
                            $facets[] = array(
                                'id' => $id,
                                'hash' => $hash,
                                'parentId' => $parentIds[0],
                                'parentHash' => $parentHash
                            );
                        }
                        else {
                            $hash = RestoUtil::getHash($id);
                            $hashes[] = '"' . pg_escape_string($hash) . '"';
                            $facets[] = array(
                                'id' => $id,
                                'hash' => $hash
                            );
                        }
                        
                        /*
                         * In any case store unmodified id to hashes
                         */
                        if ($elements[$i][0] !== 'landuse_details') {
                            $hashes[] = '"' . pg_escape_string($id) . '"';
                        }
                    }
                    /*
                     * Create facet for year/month/date
                     */
                    else if ($elements[$i][0] === 'startDate' && RestoUtil::isISO8601($elements[$i][1])) {
                        $idYear = 'year:' . substr($elements[$i][1], 0, 4);
                        $hashYear = RestoUtil::getHash($idYear);
                        $idMonth = 'month:' . substr($elements[$i][1], 5, 2);
                        $hashMonth = RestoUtil::getHash($idMonth, $hashYear);
                        $idDay = 'day:' . substr($elements[$i][1], 8, 2);
                        $hashDay = RestoUtil::getHash($idDay);
                        $hashes[] = '"' . pg_escape_string($idYear) . '"';
                        $hashes[] = '"' . pg_escape_string($idMonth) . '"';
                        $hashes[] = '"' . pg_escape_string($idDay) . '"';
                        $facets[] = array(
                            'id' => $idYear,
                            'hash' => $hashYear
                        );
                        $facets[] = array(
                            'id' => $idMonth,
                            'hash' => $hashMonth,
                            'parentId' => $idYear,
                            'parentHash' => $hashYear
                        );
                        $facets[] = array(
                            'id' => $idDay,
                            'hash' => $hashDay,
                            'parentId' => $idMonth,
                            'parentHash' => $hashMonth
                        );
                    }
                }
            }
            
            /*
             * Add "updated" and "published" keywords 
             */
            $keys[] = 'updated';
            $values[] = 'now()';
            $keys[] = 'published';
            $values[] = 'now()';
            
            /*
             * Hashes column
             */
            if (count($hashes) > 0) {
                $keys[] = $model->getDbKey('hashes');
                $values[] = '\'{' . join(',', $hashes) . '}\'';
            }
            
            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');
            pg_query($this->dbh, 'INSERT INTO ' . pg_escape_string($this->dbDriver->getSchemaName($collectionName)) . '.features (' . join(',', $keys) . ') VALUES (' . join(',', $values) . ')');
            $this->dbDriver->store(RestoDatabaseDriver::FACETS, array(
                'facets' => $facets,
                'collectionName' => $collectionName
            ));
            pg_query($this->dbh, 'COMMIT');
        } catch (Exception $e) {
            pg_query($this->dbh, 'ROLLBACK');
            RestoLogUtil::httpError(500, 'Feature ' . $keys['identifier'] . ' cannot be inserted in database');
        }
    }
    
    /**
     * Remove feature from database
     * 
     * @param RestoFeature $feature
     */
    public function removeFeature($feature) {
        
        try {
            
            $this->dbDriver->query('BEGIN');
            
            /*
             * Remove facets
             */
            $featureArray = $feature->toArray();
            foreach($featureArray['properties'] as $key => $value) {
                
                /*
                 * Keywords facets
                 */
                if ($key === 'keywords') {
                    $this->removeKeywordsFacets($featureArray['properties'][$key], $featureArray['properties']['collection']);
                }
                /*
                 * Property facets
                 */
                else {
                    $this->removePropertyFacet($featureArray['properties'], $key . ':' . $value, $featureArray['properties']['collection']);
                }
                
            }
            $this->dbDriver->query('DELETE FROM ' . (isset($feature->collection) ? $this->dbDriver->getSchemaName($feature->collection->name): 'resto') . '.features WHERE identifier=\'' . pg_escape_string($feature->identifier) . '\'');
            $this->dbDriver->query('COMMIT');    
        } catch (Exception $e) {
            $this->dbDriver->query('ROLLBACK'); 
            RestoLogUtil::httpError(500, 'Cannot delete feature ' . $feature->identifier);
        }
    }
   
    /**
     * 
     * Prepare an SQL WHERE clause from input filterName
     * 
     * @param RestoModel $model (with model keys)
     * @param array $requestParams (with model keys)
     * @param string $filterName
     * @param boolean $exclusion : if true, exclude instead of include filter (WARNING ! only works for geometry and keywords)
     * 
     */
    private function prepareFilterQuery($model, $requestParams, $filterName, $exclusion = false) {

        /*
         * First check if filter is valid and as an associated column within database
         */
        if (empty($requestParams[$filterName]) || !$model->getDbKey($model->searchFilters[$filterName]['key'])) {
            return null;
        }
        
        /*
         * Get filter type
         */
        $type = $model->getDbType($model->searchFilters[$filterName]['key']);
        
        /*
         * Special case - dates
         */
        if ($type === 'date') {
            return $this->prepareFilterQuery_date();
        }

        /*
         * Prepare filter from operation
         */
        switch ($model->searchFilters[$filterName]['operation']) {
            
            /*
             * Keywords i.e. searchTerms
             */
            case 'keywords':
                return $this->prepareFilterQuery_keywords($model, $filterName, $requestParams, $exclusion);
            /*
             * Intersects i.e. geo:*
             */
            case 'intersects':
                return $this->prepareFilterQuery_intersects($model, $filterName, $requestParams, $exclusion);
            /*
             * Distance i.e. geo:lon, geo:lat and geo:radius
             */
            case 'distance':
                return $this->prepareFilterQuery_distance($model, $filterName, $requestParams, $exclusion);
            /*
             * Intervals 
             */
            case 'interval':
                return $this->prepareFilterQuery_interval($model, $filterName, $requestParams);
            /*
             * Simple case - non 'interval' operation on value or arrays
             */
            default:
                return $this->prepareFilterQuery_general($model, $filterName, $requestParams, $type);
        }
    }
    
    /**
     * Return an array of database column names
     * 
     * @param RestoModel $model
     * @param array $excluded : list of fields to exclude from request
     * @return array
     */
    private function getSQLFields($model, $excluded = array()) {

        /*
         * Get Controller database fields
         */
        $columns = array();
        foreach (array_keys($model->properties) as $key) {

            /*
             * Avoid null value and excluded fields
             */
            if (!isset($model->properties[$key]) || in_array($key, $excluded)) {
                continue;
            }
            
            $value = is_array($model->properties[$key]) ? $model->properties[$key]['name'] : $model->properties[$key];
            
            /*
             * Force geometry element to be retrieved as GeoJSON
             * Retrieve also BoundinBox in EPSG:4326
             */
            if ($key === 'geometry') {
                $columns[] = 'ST_AsGeoJSON(' . $value . ') AS ' . $key;
                $columns[] = 'Box2D(' . $value . ') AS bbox4326';
            }
            else if ($model->getDbType($key) === 'date') {
                $columns[] = 'to_char(' . $value . ', \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') AS "' . $key . '"';
            }
            /*
             * Other fields are retrieved normally
             */
            else {
                $columns[] = $value . ' AS "' . $key . '"';
            }
        }

        return $columns;
        
    }
    
    /**
     * 
     * Convert an array of strings to the correct type
     * (Since pg_fetch_assoc returns only strings whatever the PostgreSQL type
     * we need to cast each feature properties to the right type)
     * 
     * @param RestoModel $model
     * @param Array $pgResult : pg_fetch_assoc result
     * @return array
     */
    private function correctTypes($model, $pgResult) {
        
        if (!isset($pgResult) || !is_array($pgResult)) {
            return null;
        }
        
        /*
         * PostgreSQL output columns are treated as string
         * thus they need to be converted to their true type
         */
        foreach ($pgResult as $key => $value) {
            
            if ($key === 'bbox4326') {
                $pgResult[$key] = str_replace(' ', ',', substr(substr($pgResult[$key], 0, strlen($pgResult[$key]) - 1), 4));
                      
                /*
                 * Compute EPSG:3857 bbox
                 */
                $pgResult['bbox3857'] = RestoGeometryUtil::bboxToMercator($pgResult[$key]);
            
            }
            else if ($key === 'totalcount') {
                $pgResult[$key] = (integer) $value;
            }
            else {
                $pgResult[$key] = $this->castExplicit($model, $key, $value, isset($pgResult['collection']) ? $pgResult['collection'] : null);
            }
        }
        
        return $pgResult;
    }
    
    /**
     * Explicitely cast $value from $model
     * 
     * @param RestoModel $model
     * @param string $key
     * @param string $value
     */
    private function castExplicit($model, $key, $value, $collectionName) {
        switch($model->getDbType($key)) {
            case 'integer':
                return (integer) $value;
            case 'float':
                return (float) $value;
            /*
             * PostgreSQL returns ST_AsGeoJSON(geometry) 
             */
            case 'geometry':
                return json_decode($value, true);
            case 'hstore':
                return $this->hstoreToKeywords($value, $model->context->baseUrl . 'api/collections' . (isset($collectionName) ? '/' . $collectionName : '' ) . '/search.json', $model);
            case 'array':
                return explode(',', substr($value, 1, -1));
            default:
                return $value;
        }
    }
    
    /**
     * 
     * Return keyword array assuming an input hstore $string 
     * 
     * Note : $string format is "type:name" => urlencode(json)
     *
     *      e.g. "continent:oceania"=>"%7B%22hash%22%3A%2262f4365c66c1f64%22%7D", "country:australia"=>"%7B%22hash%22%3A%228f36daace0ea948%22%2C%22parentHash%22%3A%2262f4365c66c1f64%22%2C%22value%22%3A100%7D"
     * 
     * 
     * Structure of output is 
     *      array(
     *          "id" => // Keyword id (optional)
     *          "type" => // Keyword type
     *          "value" => // Keyword value if it make sense
     *          "href" => // RESTo search url to get keyword
     *      )
     * 
     * @param string $hstore
     * @param string $url : Base url for setting href links
     * @param RestoModel $model
     * @return array
     */
    private function hstoreToKeywords($hstore, $url, $model) {
        
        if (!isset($hstore)) {
            return null;
        }
        
        $json = json_decode('{' . str_replace('}"', '}', str_replace('\"', '"', str_replace('"{', '{', str_replace('"=>"', '":"', $hstore)))) . '}', true);
        
        if (!isset($json) || !is_array($json)) {
            return null;
        }
        
        $keywords = array();
        foreach ($json as $key => $value) {

            /*
             * $key format is "type:id"
             */
            list($type, $id) = explode(':', $key, 2);
            $hrefKey = $key;
            
            /*
             * Do not display landuse_details
             */
            if ($type === 'landuse_details') {
                continue;
            }

            /*
             * Value format is urlencode(json)
             */
            $properties = json_decode(urldecode($value), true); 
            if (!isset($properties['name'])) {
                $properties['name'] = trim($model->context->dictionary->getKeywordFromValue($id, $type));
                if (!isset($properties['name'])) {
                    $properties['name'] = ucwords($id);
                }
                $hrefKey = $properties['name'];
            }
            $keywords[] = array(
                'name' => isset($properties['name']) && $properties['name'] !== '' ? $properties['name'] : $key,
                'id' => $key,
                'href' => RestoUtil::updateUrl($url, array($model->searchFilters['language']['osKey'] => $model->context->dictionary->language,  $model->searchFilters['searchTerms']['osKey'] => count(explode(' ', $hrefKey)) > 1 ? '"'. $hrefKey . '"' : $hrefKey))
            );
            foreach (array_keys($properties) as $property) {
                if (!in_array($property, array('name', 'id'))) {
                    $keywords[count($keywords) - 1][$property] = $properties[$property];
                }
            }
        }

        return $keywords;
    }
 
    /**
     * Check that mandatory filters are set
     * 
     * @param RestoModel $model
     * @param Array $params
     * @return boolean
     */
    private function checkMandatoryFilters($model, $params) {
        $missing = array();
        foreach (array_keys($model->searchFilters) as $filterName) {
            if (isset($model->searchFilters[$filterName])) {
                if (isset($model->searchFilters[$filterName]['minimum']) && $model->searchFilters[$filterName]['minimum'] === 1 && (!isset($params[$filterName]))) {
                    $missing[] = $filterName;
                }
            } 
        }
        if (count($missing) > 0) {
            RestoLogUtil::httpError(400, 'Missing mandatory filter(s) ' . join(', ', $filterName));
        }
        
        return true;
        
    }
    
    /**
     * Return search filters based on model and input search parameters
     * 
     * @param RestoModel $model
     * @param Array $params
     * @return boolean
     */
    private function prepareFilters($model, $params) {
        
        
        /*
         * Remove box filter if location filter is set
         */
        /* TODO
        if (isset($params['geo:name'])) {
            unset($params['geo:box']);
        }
        else {
            if (isset($params['searchTerms'])) {
                $splitted = RestoUtil::splitString($params['searchTerms']);
                for ($i = count($splitted); $i--;) {
                    $arr = explode(':', $splitted[$i]);
                    if ($arr[0] === 'continent' || $arr[0] === 'country' || $arr[0] === 'region' || $arr[0] === 'state' || $arr[0] === 'city') {
                        unset($params['geo:box']);
                    }
                    if ($arr[0] === 'country') {
                        $countryName = $arr[1];
                    }
                    if ($arr[0] === 'state') {
                        $stateName = $arr[1];
                    }
                    if ($arr[0] === 'city') {
                        $cityName = $arr[1];
                    }
                }
                
                
                if (isset($cityName)) {
                    if (isset($model->context->modules['Gazetteer'])) {
                        $gazetteer = new Gazetteer($model->context, $model->user, $model->context->modules['Gazetteer']);
                        $locations = $gazetteer->search(array(
                            'q' => $cityName,
                            'country' => isset($countryName) ? $countryName : null,
                            'state' => isset($stateName) ? $stateName : null
                            )
                        );
                        if (count($locations) > 0) {
                            $params['geo:name'] = $locations[0]['name'] . ($locations[0]['countryname'] !== '' ? ', ' . $locations[0]['countryname'] : '');
                            $params['geo:lon'] = $locations[0]['longitude'];
                            $params['geo:lat'] = $locations[0]['latitude'];
                        }
                    }
                }
            }
        }
        */
        /*
         * Only visible features are returned
         */
        $filters = array(
            'visible=1'
        );
        
        /*
         * Process each input search filter excepted excluded filters
         */
        foreach (array_keys($model->searchFilters) as $filterName) {
            if (!in_array($filterName, $this->excludedFilters)) {
                $filter = $this->prepareFilterQuery($model, $params, $filterName);
                if (isset($filter) && $filter !== '') {
                    $filters[] = $filter;
                }
            }
        }
        
        return $filters;
        
    }
    
    /**
     * Remove keywords facets
     * 
     * @param array $keywords
     * @param string $collectionName
     */
    private function removeKeywordsFacets($keywords, $collectionName) {
        for ($i = count($keywords); $i--;) {
            if (isset($keywords[$i]['hash'])) {
                $this->dbDriver->remove(RestoDatabaseDriver::FACET, array(
                    'hash' => $keywords[$i]['hash'],
                    'collectionName' => $collectionName
                ));
            }
        }
    }
    
    /**
     * Remove non-keywords facets
     * 
     * @param array $properties
     * @param array $id
     * @param string $collectionName
     */
    private function removePropertyFacet($properties, $id, $collectionName) {
        
        if (!$this->dbDriver->facetUtil->getFacetCategory($id)) {
            return false;
        }
                
        $parentHash = null;
        list($parentType) = explode(':', $id, 1);
        $parentIds = array();
        while (isset($parentType)) {
            $parentType = $this->dbDriver->facetUtil->getFacetParentType($parentType);
            foreach ($properties as $pKey => $pValue) {
                if ($pKey === $parentType && $pValue) {
                    $parentIds[] = $parentType . ':' . $pValue;
                    break;
                }
            }
        }
        if (count($parentIds) > 0) {
            for ($k = count($parentIds); $k--;) {
                $parentHash = RestoUtil::getHash($parentIds[$k], $parentHash);
            }
        }
        
        return $this->dbDriver->remove(RestoDatabaseDriver::FACET, array(
           'hash' => RestoUtil::getHash($id, $parentHash),
           'collectionName' => $collectionName
        ));
        
    }
    
    /**
     * Prepare SQL query for date
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $filters
     * @return type
     */
    private function prepareFilterQuery_date($model, $filterName, $filters) {
        
        if (!RestoUtil::isISO8601($filters[$filterName])) {
            RestoLogUtil::httpError(400, 'Invalid date parameter - ' . $filterName);
        }
        
        /*
         * Process time:start and time:end filters
         */
        switch ($filterName) {
            case 'time:start':
                return $model->getDbKey($model->searchFilters['time:start']['key']) . ' >= \'' . pg_escape_string($filters['time:start']) . '\'';
            case 'time:end':
                return $model->getDbKey($model->searchFilters['time:end']['key']) . ' <= \'' . pg_escape_string($filters['time:end']) . '\'';
            default:
                return null;
        }
    }
    
    /**
     * Prepare SQL query for non 'interval' operation on value or arrays
     * If operation is '=' and last character of input value is a '%' sign then perform a like instead of an =
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $requestParams
     * @paral string $type
     * @return type
     */
    private function prepareFilterQuery_general($model, $filterName, $requestParams, $type) {
        
        if (!is_array($requestParams[$filterName])) {
            $requestParams[$filterName] = array($requestParams[$filterName]);
        }
        
        /*
         * Set quote to "'" for non numeric filter types
         */
        $quote = $type === 'numeric' ? '' : '\'';
        
        /*
         * Set operation
         */
        $operation = $model->searchFilters[$filterName]['operation'];
        
        /*
         * Array of values assumes a 'OR' operation
         */
        $ors = array();
        for ($i = count($requestParams[$filterName]); $i--;) {
            
            /*
             * LIKE case
             */
            if ($operation === '=' && substr($requestParams[$filterName][$i], -1) === '%') {
                $ors[] = $model->getDbKey($model->searchFilters[$filterName]['key']) . ' LIKE ' . $quote . pg_escape_string($requestParams[$filterName][$i]) . $quote;
            }
            /*
             * Otherwise use operation
             */
            else {
                $ors[] = $model->getDbKey($model->searchFilters[$filterName]['key']) . ' ' . $operation . ' ' . $quote . pg_escape_string($requestParams[$filterName][$i]) . $quote;
            }
        }
        
        return count($ors) > 1 ? '(' . join(' OR ', $ors) . ')' : $ors[0];
    }
    
    /**
     * Prepare SQL query for spatial operation ST_Intersects (Input bbox or polygon)
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $requestParams
     * @param boolean $exclusion
     * @return type
     */
    private function prepareFilterQuery_intersects($model, $filterName, $requestParams, $exclusion) {
        
        /*
         * Default bounding box is the whole earth
         */
        if ($filterName === 'geo:box') {
            $coords = explode(',', $requestParams[$filterName]);
            if (count($coords) !== 4) {
                RestoLogUtil::httpError(400, 'Invalid geo:box');
            }
            $lonmin = is_numeric($coords[0]) ? $coords[0] : -180;
            $latmin = is_numeric($coords[1]) ? $coords[1] : -90;
            $lonmax = is_numeric($coords[2]) ? $coords[2] : 180;
            $latmax = is_numeric($coords[3]) ? $coords[3] : 90;
            return ($exclusion ? 'NOT ' : '') . 'ST_intersects(' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ", ST_GeomFromText('" . pg_escape_string('POLYGON((' . $lonmin . ' ' . $latmin . ',' . $lonmin . ' ' . $latmax . ',' . $lonmax . ' ' . $latmax . ',' . $lonmax . ' ' . $latmin . ',' . $lonmin . ' ' . $latmin . '))') . "', 4326))";
        }
        else if ($filterName === 'geo:geometry') {
            return ($exclusion ? 'NOT ' : '') . 'ST_intersects(' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ", ST_GeomFromText('" . pg_escape_string($requestParams[$filterName]) . "', 4326))";
        }
    }
    
    /**
     * Prepare SQL query for spatial operation ST_Distance (Input bbox or polygon)
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $requestParams
     * @param boolean $exclusion
     * @return type
     */
    private function prepareFilterQuery_distance($model, $filterName, $requestParams, $exclusion) {
        
        /*
         * WARNING ! Quick benchmark show that st_distance is 100x slower than st_intersects
         * TODO - check if st_distance performance can be improved.
         */
        $use_distance = false;

        /*
         * geo:lon and geo:lat have preseance to geo:name
         * (avoid double call to Gazetteer)
         */
        if (isset($requestParams['geo:lon']) && isset($requestParams['geo:lat'])) {
            $radius = RestoGeometryUtil::radiusInDegrees(isset($requestParams['geo:radius']) ? floatval($requestParams['geo:radius']) : 10000, $requestParams['geo:lat']);
            if ($use_distance) {
                return 'ST_distance(' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ', ST_GeomFromText(\'' . pg_escape_string('POINT(' . $requestParams['geo:lon'] . ' ' . $lat = $requestParams['geo:lat'] . ')') . '\', 4326)) < ' . $radius;
            }
            else {
                $lonmin = $requestParams['geo:lon'] - $radius;
                $latmin = $requestParams['geo:lat'] - $radius;
                $lonmax = $requestParams['geo:lon'] + $radius;
                $latmax = $requestParams['geo:lat'] + $radius;
                return ($exclusion ? 'NOT ' : '') . 'ST_intersects(' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ", ST_GeomFromText('" . pg_escape_string('POLYGON((' . $lonmin . ' ' . $latmin . ',' . $lonmin . ' ' . $latmax . ',' . $lonmax . ' ' . $latmax . ',' . $lonmax . ' ' . $latmin . ',' . $lonmin . ' ' . $latmin . '))') . "', 4326))";
            }
        }
    }
    
    /**
     * Prepare SQL query for keywords- i.e. searchTerms
     * 
     * !! IMPORTANT NOTE !!
     * 
     *      keywords are stored in hstore 'keywords' column
     *      BUT searches are done on the array 'hashes' column
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $requestParams
     * @param boolean $exclusion
     * @return type
     */
    private function prepareFilterQuery_keywords($model, $filterName, $requestParams, $exclusion) {
        
        $terms = array();
        $splitted = RestoUtil::splitString($requestParams[$filterName]);
        $key = $model->getDbKey($model->searchFilters[$filterName]['key']);
        $filters = array(
            'with' => array(),
            'without' => array()
        );
        for ($i = 0, $l = count($splitted); $i < $l; $i++) {

            /*
             * If term as a '-' prefix then performs a "NOT keyword"
             * If keyword contain a + then transform it into a ' '
             */
            $searchTerm = ($exclusion ? '-' : '') . $splitted[$i];
            $not = false;
            if (substr($searchTerm, 0, 1) === '-') {
                $not = true;
                $searchTerm = substr($searchTerm, 1);
            }

            /*
             * Keywords structure is "type:value"
             */
            $typeAndValue = explode(':', $searchTerm);
            if (count($typeAndValue) !== 2) {
                RestoLogUtil::httpError(400, 'Invalid keyword strucuture ' . $searchTerm);
            }

            /*
             * Landuse columns are NUMERIC columns
             */
            if ($typeAndValue[0] === 'landuse') {
                if (in_array($typeAndValue[1], array('cultivated', 'desert', 'flooded', 'forest','herbaceous','snow','ice','urban','water'))) {
                    $terms[] = 'lu_' . $typeAndValue[1] . ($not ? ' = ' : ' > ') . '0';
                }
                else {
                    RestoLogUtil::httpError(400, 'Invalid landuse - should be numerice value ');
                }
            }
            /*
             * TODO - need to be rewritten (see search(...))
             */
            else if ($typeAndValue[0] === 'city') {
                continue;
            }
            /*
             * Everything other types are stored within hashes column
             * If input keyword is a hash leave value unchanged
             * 
             * Structure is :
             * 
             *      type:id or type:id1|id2|id3|.etc.
             * 
             * In second case, '|' is understood as "OR"
             */
            else {
                $ors = array();
                $arr = explode('|', $typeAndValue[1]);
                if (count($arr) > 1) {
                    for ($j = count($arr); $j--;) {
                        $ors[] = $key . " @> ARRAY['" . pg_escape_string($typeAndValue[0] !== 'hash' ? $typeAndValue[0] . ':' . $arr[$j] : $arr[$j]) . "']";
                    }
                    if (count($ors) > 1) {
                        $terms[] = ($not ? 'NOT (' : '(') . join(' OR ', $ors) . ')';
                    }
                }
                else {
                    $filters[$not ? 'without' : 'with'][] = "'" . pg_escape_string($typeAndValue[0] !== 'hash' ? $searchTerm : $typeAndValue[1]) . "'";
                }
            }
        }

        if (count($filters['without']) > 0) {
            $terms[] = 'NOT ' . $key . " @> ARRAY[" . join(',', $filters['without']) . "]";
        }
        if (count($filters['with']) > 0) {
            $terms[] = $key . " @> ARRAY[" . join(',', $filters['with']) . "]";
        }

        return join(' AND ', $terms);

    }
    
    /**
     * Prepare SQL query for intervals
     * 
     * If
     *      A is the value of $this->request['params'][$this->description['searchFilters'][$filterName]['osKey']]
     * Then
     *      A = n1 then returns value = n1
     *      A = {n1,n2} then returns  value = n1 or value = n2
     *      A = [n1,n2] then returns  n1 ≤ value ≤ n2
     *      A = [n1,n2[ then returns  n1 ≤ value < n2
     *      A = ]n1,n2[ then returns  n1 < value < n2
     *      A = ]n1 then returns n1 < value
     *      A = [n1 then returns  n1 ≤ value
     *      A = n1[ then returns value < n2
     *      A = n1] then returns value ≤ n2 
     * 
     * @param RestoModel $model
     * @param string $filterName
     * @param array $requestParams
     * @return type
     */
    private function prepareFilterQuery_interval($model, $filterName, $requestParams) {
        
        $values = explode(',', $requestParams[$filterName]);

        /*
         * No ',' present i.e. simple equality or non closed interval
         */
        if (count($values) === 1) {

            /* 
             * Non closed interval
             */
            $op1 = substr(trim($values[0]), 0, 1);
            $val1 = substr(trim($values[0]), 1);
            if ($op1 === '[' || $op1 === ']') {
                return $model->getDbKey($model->searchFilters[$filterName]['key']) . ($op1 === '[' ? ' >= ' : ' > ') . pg_escape_string($val1);
            }
            $op2 = substr(trim($values[0]), -1);
            $val2 = substr(trim($values[0]), 0, strlen(trim($values[0])) - 1);
            if ($op2 === '[' || $op2 === ']') {
                return $model->getDbKey($model->searchFilters[$filterName]['key']) . ($op2 === ']' ? ' <= ' : ' < ') . pg_escape_string($val2);
            }
            /*
             * Simple equality
             */
            return $model->getDbKey($model->searchFilters[$filterName]['key']) . ' = ' . pg_escape_string($requestParams[$filterName]);
        }
        /*
         * Two values
         */
        else if (count($values) === 2) {

            /*
             * First and last characters give operators
             */
            $op1 = substr(trim($values[0]), 0, 1);
            $val1 = substr(trim($values[0]), 1);
            $op2 = substr(trim($values[1]), -1);
            $val2 = substr(trim($values[1]), 0, strlen(trim($values[1])) - 1);

            /*
             * A = {n1,n2} then returns  = n1 or = n2
             */
            if ($op1 === '{' && $op2 === '}') {
                return '(' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ' = ' . pg_escape_string($val1) . ' OR ' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ' = ' . pg_escape_string($val2) . ')';
            }

            /*
             * Other cases i.e. 
             * A = [n1,n2] then returns <= n1 and <= n2
             * A = [n1,n2[ then returns <= n1 and B < n2
             * A = ]n1,n2[ then returns < n1 and B < n2
             * 
             */
            if (($op1 === '[' || $op1 === ']') && ($op2 === '[' || $op2 === ']')) {
                return $model->getDbKey($model->searchFilters[$filterName]['key']) . ($op1 === '[' ? ' >= ' : ' > ') . pg_escape_string($val1) . ' AND ' . $model->getDbKey($model->searchFilters[$filterName]['key']) . ($op2 === ']' ? ' <= ' : ' < ') . pg_escape_string($val2);
            }
        }
        
    }
}
