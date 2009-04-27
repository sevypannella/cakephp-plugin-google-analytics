<?php
require_once ROOT.DS.APP_DIR.DS.'plugins'.DS.'google_analytics'.DS.'config'.DS.'google_analytics.php';
require_once ROOT.DS.APP_DIR.DS.'plugins'.DS.'google_analytics'.DS.'models'.DS.'datasources'.DS.'google_analytics_source.php';

class GoogleAnalyticsSourceTest extends CakeTestCase
{
    function startTest()
    {
        // instead of ConnectionManager::getDataSource() we build it manually
        // to be able to specify $autoConnect => false
        $config =& new GOOGLE_ANALYTICS_CONFIG();
        $this->db =& new GoogleAnalyticsSource(
            $config->googleAnalytics_test, false);
    }

    function test___buildParams()
    {
        $result = $this->db->__buildParams(array(
            'conditions' => array(
                'dimensions' => 'country',
                'metrics' => 'newVisits',
                'sort' => 'newVisits')));
        $expected = array(
            'dimensions' => 'ga:country',
            'metrics' => 'ga:newVisits',
            'sort' => 'ga:newVisits');
        $this->assertEqual($result, $expected,
            "should append ga: to single parameters : %s");

        $result = $this->db->__buildParams(array(
            'conditions' => array(
                'dimensions' => array('country', 'city'),
                'metrics' => array('newVisits', 'uniquePageviews'),
                'sort' => array('newVisits', 'city'))));
        $expected = array(
            'dimensions' => 'ga:country,ga:city',
            'metrics' => 'ga:newVisits,ga:uniquePageviews',
            'sort' => 'ga:newVisits,ga:city');
        $this->assertEqual($result, $expected,
            "should append ga: to array parameters : %s");

        $result = $this->db->__buildParams(array(
            'conditions' => array(
                'sort' => '-city')));
        $expected = array('sort' => '-ga:city');
        $this->assertEqual($result, $expected,
            "should correctly treat the minus on a single sort : %s");

        $result = $this->db->__buildParams(array(
            'conditions' => array(
                'sort' => array('newVisits', '-city'))));
        $expected = array('sort' => 'ga:newVisits,-ga:city');
        $this->assertEqual($result, $expected,
            "should correctly treat the minus on a multiple sort : %s");
    }

    function test___validateQueryData()
    {
        $result = $this->db->__validateQueryData(array(
            'conditions' => array()));
        $this->assertError('start-date is required');
        $this->assertIdentical($result, null,
            "should return null when start-date is missing : %s");

        $result = $this->db->__validateQueryData(array(
            'conditions' => array(
                'start-date' => '2009-01-01')));
        $this->assertError('end-date is required');
        $this->assertIdentical($result, null,
            "should return null when start-date is missing : %s");

        $result = $this->db->__validateQueryData(array(
                'conditions' => array(
                    'start-date' => '2009-01-01',
                    'end-date' => '2009-02-01',
                    'dimensions' => array('a','b','c','d','e','f','g','h'))));
        $this->assertError('too many dimensions, the maximum allowed is 7');
        $this->assertIdentical($result, null,
            "should return null when too many dimensions are given : %s");

        $result = $this->db->__validateQueryData(array(
                'conditions' => array(
                    'start-date' => '2009-01-01',
                    'end-date' => '2009-02-01',
                    'metrics' => array(
                        'a','b','c','d','e','f','g','h', 'i', 'j', 'k', 'l'))));
        $this->assertError('too many metrics, the maximum allowed is 10');
        $this->assertIdentical($result, null,
            "should return null when too many metrics are given : %s");

        $result = $this->db->__validateQueryData(array(
                'conditions' => array(
                    'start-date' => '2010-01-01',
                    'end-date' => '2009-01-01')));
        $this->assertError('date order is reversed');
        $this->assertIdentical($result, null,
            "should return null when date order is reversed : %s");
    }

    function test_listSources()
    {
        $this->assertIdentical($this->db->listSources(), false,
            "should return false : %s");
    }

    function test_read()
    {
        Mock::generatePartial(
            'GoogleAnalyticsSource',
            'GoogleAnalyticsSourceMock',
            array('accounts', 'account_data'));
        $mock =& new GoogleAnalyticsSourceMock($this);
        $mock->setReturnValue('accounts', 'accounts');
        $mock->setReturnValue('account_data', 'account_data');
    
        $result = $mock->read($model, array());
        $this->assertEqual($result, 'accounts',
            "should call accounts() when given no parameters : %s");
    
        $result = $mock->read($model, array(
            'conditions' => array('profileId' => 123456)));
        $this->assertEqual($result, 'account_data',
            "should call account_data() when given a profileId : %s");
    
        //TODO test that Model->find('all') and Model->find('first') trigger
        // the appropriate calls from read()
    }

    function test___parseDataPoint()
    {
        $result = $this->db->__parseDataPoint(array(
            'name' => 'ga:country',
            'value' => 'France'));
        $expected = array('name' => 'country', 'value' => 'France');
        $this->assertEqual($result, $expected,
            "should remove ga: from a single dimension datapoint : %s");

        $result = $this->db->__parseDataPoint(array(
            'confidenceInterval' => '0.0',
            'name' => 'ga:uniquePageViews',
            'type' => 'integer',
            'value' => 1));
        $expected = array(
            'confidenceInterval' => '0.0',
            'name' => 'uniquePageViews',
            'type' => 'integer',
            'value' => 1);
        $this->assertEqual($result, $expected,
            "should remove ga: from a single metric datapoint : %s");

        $result = $this->db->__parseDataPoint(array(
            array('name' => 'ga:country', 'value' => 'France'),
            array('name' => 'ga:country', 'value' => 'United States')));
        $expected = array(
            array('name' => 'country', 'value' => 'France'),
            array('name' => 'country', 'value' => 'United States'));
        $this->assertEqual($result, $expected,
            "should remove ga: from several dimension datapoints : %s");

        $result = $this->db->__parseDataPoint(array(
            array(
                'confidenceInterval' => '0.0',
                'name' => 'ga:uniquePageviews',
                'type' => 'integer',
                'value' => 1),
            array(
                'confidenceInterval' => '0.0',
                'name' => 'ga:newVisits',
                'type' => 'integer',
                'value' => 1)));
        $expected = array(
            array(
                'confidenceInterval' => '0.0',
                'name' => 'uniquePageviews',
                'type' => 'integer',
                'value' => 1),
            array(
                'confidenceInterval' => '0.0',
                'name' => 'newVisits',
                'type' => 'integer',
                'value' => 1));
        $this->assertEqual($result, $expected,
            "should remove ga: from several metric datapoints : %s");
    }

    function test___dataPoints()
    {
        $result = $this->db->__dataPoints(array('Entry' => array(
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => array(
                    'value' => 'ga:country=France',
                    'type' => 'text'),
                'Link' => array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => 'http://www.google.com/analytics'),
                'Dimension' => array(
                    'name' => 'ga:country',
                    'value' => 'France'),
                'Metric' => array(
                    'confidenceInterval' => '0.0',
                    'name' => 'ga:uniquePageviews',
                    'type' => 'integer',
                    'value' => 1)),
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => array(
                    'value' => 'ga:country=United States',
                    'type' => 'text'),
                'Link' => array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => 'http://www.google.com/analytics'),
                'Dimension' => array(
                    'name' => 'ga:country',
                    'value' => 'United States'),
                'Metric' => array(
                    'confidenceInterval' => '0.0',
                    'name' => 'ga:uniquePageviews',
                    'type' => 'integer',
                    'value' => 2)))));

        $expected = array(
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => 'ga:country=France',
                'dimensions' => array(
                    'name' => 'country',
                    'value' => 'France'),
                'metrics' => array(
                    'confidenceInterval' => '0.0',
                    'name' => 'uniquePageviews',
                    'type' => 'integer',
                    'value' => 1)),
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => 'ga:country=United States',
                'dimensions' => array(
                    'name' => 'country',
                    'value' => 'United States'),
                'metrics' => array(
                    'confidenceInterval' => '0.0',
                    'name' => 'uniquePageviews',
                    'type' => 'integer',
                    'value' => 2)));

        $this->assertEqual($result, $expected,
            "should reformat datapoints for a single dimension and metric : %s");

        $result = $this->db->__dataPoints(array('Entry' => array(
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => array(
                    'value' => 'ga:country=France',
                    'type' => 'text'),
                'Link' => array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => 'http://www.google.com/analytics'),
                'Dimension' => array(
                    array('name' => 'ga:country', 'value' => 'France'),
                    array('name' => 'ga:city', 'value' => 'Caen')),
                'Metric' => array(
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'ga:uniquePageviews',
                        'type' => 'integer',
                        'value' => 1),
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'ga:newVisits',
                        'type' => 'integer',
                        'value' => 1))),
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => array(
                    'value' => 'ga:country=United States',
                    'type' => 'text'),
                'Link' => array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => 'http://www.google.com/analytics'),
                'Dimension' => array(
                    array('name' => 'ga:country', 'value' => 'United States'),
                    array('name' => 'ga:city', 'value' => 'Atlanta')),
                'Metric' => array(
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'ga:uniquePageviews',
                        'type' => 'integer',
                        'value' => 2),
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'ga:newVisits',
                        'type' => 'integer',
                        'value' => 2))))));

        $expected = array(
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => 'ga:country=France',
                'dimensions' => array(
                    array('name' => 'country', 'value' => 'France'),
                    array('name' => 'city', 'value' => 'Caen')),
                'metrics' => array(
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'uniquePageviews',
                        'type' => 'integer',
                        'value' => 1),
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'newVisits',
                        'type' => 'integer',
                        'value' => 1))),
            array(
                'id' => 'http://www.google.com/analytics/feed/data?ids=xxx',
                'updated' => '2009-01-01',
                'title' => 'ga:country=United States',
                'dimensions' => array(
                    array('name' => 'country', 'value' => 'United States'),
                    array('name' => 'city', 'value' => 'Atlanta')),
                'metrics' => array(
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'uniquePageviews',
                        'type' => 'integer',
                        'value' => 2),
                    array(
                        'confidenceInterval' => '0.0',
                        'name' => 'newVisits',
                        'type' => 'integer',
                        'value' => 2))));

        $this->assertEqual($result, $expected,
            "should reformat datapoints for multiple dimensions and metrics : %s");

        $this->assertIdentical($this->db->__dataPoints(array()), array(),
            "should return array() with empty parameters : %s");
        
    }
}