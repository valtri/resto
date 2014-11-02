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

/*
 * English Dictionary class
 */

class RestoDictionary_en extends RestoDictionary {

    protected $dictionary = array(
        
        /*
         * List of words in the query that are
         * considered as 'noise' for the query analysis
         * and thus excluded from the analysis
         */
        'excluded' => array(
            'than',
            'over',
            'acquired',
            'image',
            'images',
            'cover',
            'area',
            'zone'
        ),
        /*
         * Modifiers
         * 
         * For each entry 
         *   - the key (left side) is the modifier key
         *   - the value (right side) is an array of modifier homonyms
         *     in the given language. The first value is the prefered one
         *   
         */
        'modifiers' => array(
            'after' => array('after'),
            'ago' => array('ago'),
            'and' => array('and'),
            'before' => array('before'),
            'between' => array('between'),
            'equal' => array('equal'),
            'greater' => array('greater', 'more', '>'),
            'last' => array('last'),
            'lesser' => array('lesser', '<', 'less', 'lower'),
            'since' => array('since'),
            'today' => array('today'),
            'with' => array('with', 'containing'),
            'without' => array('without', 'no'),
            'yesterday' => array('yesterday')
        ),
        /*
         * Units
         * 
         * For each entry 
         *   - the key (left side) is the unit key
         *   - the value (right side) is an array of unit homonyms
         *     in the given language. The first value is the prefered one
         * 
         */
        'units' => array(
            'm' => array('m', 'meter', 'meters'),
            'km' => array('km', 'kilometer', 'kilometers'),
            '%' => array('%', 'percent', 'percents', 'percentage'),
            'days' => array('days', 'day'),
            'months' => array('month', 'months'),
            'years' => array('year', 'years')
        ),
        /*
         * Numbers
         * 
         * For each entry 
         *   - the key (left side) is the number key
         *   - the value (right side) is an array of number homonyms
         *     in the given language. The first value is the prefered one
         * 
         */
        'numbers' => array(
            '1' => array('one'),
            '2' => array('two'),
            '3' => array('three'),
            '4' => array('four'),
            '5' => array('five'),
            '6' => array('six'),
            '7' => array('seven'),
            '8' => array('eight'),
            '9' => array('nine'),
            '10' => array('ten'),
            '100' => array('hundred'),
            '1000' => array('thousand')
        ),
        /*
         * Months
         * 
         * For each entry 
         *   - the key (left side) is the month key
         *   - the value (right side) is an array of month homonyms
         *     in the given language. The first value is the prefered one
         * 
         */
        'months' => array(
            '01' => array('january'),
            '02' => array('february'),
            '03' => array('march'),
            '04' => array('april'),
            '05' => array('may'),
            '06' => array('june'),
            '07' => array('july'),
            '08' => array('august'),
            '09' => array('september'),
            '10' => array('october'),
            '11' => array('november'),
            '12' => array('december')
        ),
        /*
         * Quantities
         * 
         * For each entry 
         *   - the key (left side) is the quantity key
         *   - the value (right side) is an array of quantity homonyms
         *     in the given language. The first value is the prefered one
         * 
         */
        'quantities' => array(
            'resolution' => array('resolution'),
            'orbit' => array('orbit'),
            'cloud' => array('cloud', 'clouds'),
            'snow' => array('snow'),
            'ice' => array('ice'),
            'urban' => array('urban', 'city', 'cities', 'urban area'),
            'cultivated' => array('cultivated', 'cultivated area', 'cropland', 'croplands', 'crop', 'crops'),
            'forest' => array('forest', 'forests'),
            'herbaceous' => array('herbaceous', 'herbaceous area', 'grass'),
            'desert' => array('desert', 'bare area'),
            'flooded' => array('flooded'),
            'water' => array('water')
        )
    );
    
    /*
     * Translations
     */
    protected $translations = array(
        '_headerTitle' => 'resto',
        '_headerDescription' => '<b>resto</b> - <b>RE</b>stful <b>S</b>emantic search <b>T</b>ool for ge<b>O</b>spatial<br/>RESTo implements search service with semantic query analyzis on Earth Observation metadata database. It conforms to OGC 13-026 standard - OpenSearch Extension for Earth Observation.',
        '_selfCollectionLink' => 'self',
        '_alternateCollectionLink' => 'alternate',
        '_firstCollectionLink' => 'first',
        '_lastCollectionLink' => 'last',
        '_nextCollectionLink' => 'next',
        '_previousCollectionLink' => 'previous',
        '_selfFeatureLink' => 'self',
        '_about' => 'About',
        '_close' => 'Close',
        '_acquiredOn' => 'acquired on {a:1}',
        '_placeHolder' => 'Search - ex. {a:1}',
        '_query' => 'Search filters - {a:1}',
        '_notUnderstood' => 'Request not understood - no search filters applied',
        '_noResult' => 'Found no result - try another request !',
        '_oneResult' => '1 result',
        '_multipleResult' => '{a:1} results',
        '_firstPage' => '<<',
        '_previousPage' => 'Previous',
        '_nextPage' => 'Next',
        '_lastPage' => '>>',
        '_pageNumber' => 'Page {a:1}',
        '_identifier' => 'Identifier',
        '_resolution' => 'Resolution',
        '_startDate' => 'Start of acquisition',
        '_completionDate' => 'End of acquisition',
        '_viewMetadata' => 'View description of product {a:1}',
        '_viewMapshup' => 'View on map',
        '_viewMapshupFullResolution' => 'View on map',
        '_download' => 'Download',
        '_keywords' => 'Keywords',
        '_atomLink' => 'ATOM link for {a:1}',
        '_htmlLink' => 'HTML link for {a:1}',
        '_jsonLink' => 'GeoJSON link for {a:1}',
        '_thisResourceContainsLanduse' => 'Images that contain {a:2}',
        '_thisResourceIsLocated' => 'Images located in {a:1}',
        '_thisResourceContainsCity' => 'Images around {a:1}',
        '_thisResourceWasAcquiredBy' => 'Images acquired by {a:1} satellite',
        '_landUse' => 'Land use',
        '_location' => 'Location',
        '_platform' => 'Satellite',
        '_tags' => 'Tags',
        '_other' => 'Other',
        // landuse
        'urban' => 'Urban area',
        'cultivated' => 'Cultivated area',
        'flooded' => 'Flooded area',
        'herbaceous' => 'Herbaceous area',
        'desert' => 'Desert',
        'water' => 'Water',
        'forest' => 'Forest',
        'THR' => 'Images with resolution between 0 and 2.5 m',
        'HR' => 'Images with resolution between 2.5 and 30 m',
        'MR' => 'Images with resolution between 30 and 500 m',
        'LR' => 'Images with resolution greater than 500 m',
        '_home' => 'Home',
        '_viewAtomFeed' => 'View Atom feed for this search result',
        '_zoom' => 'Zoom map',
        '_unZoom' => 'Unzoom map',
        '_centerOnLayer' => 'Center view on search result',
        '_globalMapView' => 'Center on whole earth',
        '_showOnMap' => 'View on map',
        '_addCollection' => 'Add a collection',
        '_update' => 'Update',
        '_deactivate' => 'Deactivate',
        '_remove' => 'Remove',
        '_login' => 'Connect',
        '_logout' => 'Disconnect',
        '_dropCollection' => 'Drop a collection description file',
        '_email' => 'Email',
        '_password' => 'Password',
        '_createAccount' => 'Create an account',
        '_givenName' => 'Given name',
        '_lastName' => 'Last name',
        '_userName' => 'User name',
        '_retypePassword' => 'Retype password',
        '_back' => 'Back',
        '_signWithOauth' => 'Sign in with {a:1} account',
        '_addResource' => 'Add a resource',
        '_dropResource' => 'Drop a resource metadata file',
        '_resultFor' => 'Search results for &#34;{a:1}&#34;',
        '_resourceSummary' => '{a:1} image ({a:2} m) acquired on {a:3}',
        '_poi' => 'Points of interest',
        // Menu
        '_menu_shareOn' => 'Share on {a:1}',
        '_menu_viewCart' => 'View Cart',
        '_menu_connexion' => 'Connexion',
        '_menu_search' => 'Search...'
    );

    /**
     * Constructor
     * 
     * @param RestoDatabaseDriver $dbDriver
     * @throws Exception
     */
    public function __construct($dbDriver) {
        parent::__construct($dbDriver);
    }
    
}
