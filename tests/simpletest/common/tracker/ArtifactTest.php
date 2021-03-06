<?php

require_once('common/tracker/Artifact.class.php');

Mock::generatePartial('Artifact', 'ArtifactTestVersion', array('insertDependency', 'validArtifact', 'existDependency', 'addHistory', 'getReferenceManager', 'userCanEditFollowupComment'));

require_once('common/tracker/ArtifactFieldFactory.class.php');
Mock::generate('ArtifactFieldFactory');

require_once('common/reference/ReferenceManager.class.php');
Mock::generate('ReferenceManager');

require_once('common/tracker/ArtifactType.class.php');
Mock::generate('ArtifactType');

require_once('common/include/Codendi_HTMLPurifier.class.php');
Mock::generate('Codendi_HTMLPurifier');

require_once('www/include/utils.php');

/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * 
 * 
 *
 * Tests the class Artifact
 */
class ArtifactTest extends TuleapTestCase {


    public function testAddDependenciesSimple() {
        $a = new ArtifactTestVersion($this);
        $a->setReturnValue('insertDependency', true);
        $a->setReturnValue('validArtifact', true);
        $a->setReturnValue('existDependency', false);
        $changes = null;
        $this->assertTrue($a->addDependencies("171", $changes, false), "It should be possible to add a dependency like 171");
    }

    public function testAddWrongDependency() {
        $a = new ArtifactTestVersion($this);
        $a->setReturnValue('insertDependency', true);
        $a->setReturnValue('validArtifact', false);
        //$a->setReturnValue('existDependency', false);
        $changes = null;
        $this->assertFalse($a->addDependencies("99999", $changes, false), "It should be possible to add a dependency like 99999 because it is not a valid artifact");
        $GLOBALS['Response']->expectCallCount('addFeedback', 2);

    }

    public function testAddDependenciesDouble() {
        $a = new ArtifactTestVersion($this);
        $a->setReturnValue('insertDependency', true);
        $a->setReturnValue('validArtifact', true);
        $a->setReturnValue('existDependency', false);
        $a->setReturnValueAt(0, 'existDependency', false);
        $a->setReturnValueAt(1, 'existDependency', true);
        $changes = null;
        $this->assertTrue($a->addDependencies("171, 171", $changes, false), "It should be possible to add two identical dependencies in the same time, without getting an exception");
    }
    
    public function testFormatFollowUp() {
        $art = new ArtifactTestVersion($this);

        $txtContent = 'testing the feature';
        $htmlContent = '&lt;pre&gt;   function processEvent($event, $params) {&lt;br /&gt;       foreach(parent::processEvent($event, $params) as $key =&amp;gt; $value) {&lt;br /&gt;           $params[$key] = $value;&lt;br /&gt;       }&lt;br /&gt;   }&lt;br /&gt;&lt;/pre&gt; ';
        //the output will be delivered in a mail
        $this->assertEqual('   function processEvent($event, $params) {       foreach(parent::processEvent($event, $params) as $key => $value) {           $params[$key] = $value;       }   } ' , $art->formatFollowUp(102, 1,$htmlContent, 2));
        $this->assertEqual($txtContent, $art->formatFollowUp(102, 0, $txtContent, 2));
        
        //the output is destinated to be exported
        $this->assertEqual('<pre>   function processEvent($event, $params) {<br />       foreach(parent::processEvent($event, $params) as $key =&gt; $value) {<br />           $params[$key] = $value;<br />       }<br />   }<br /></pre> ', $art->formatFollowUp(102, 1,$htmlContent,1));
        $this->assertEqual($txtContent, $art->formatFollowUp(102, 0, $txtContent, 1));
        
        //The output will be displayed on browser
        $this->assertEqual('<pre>   function processEvent($event, $params) {<br />       foreach(parent::processEvent($event, $params) as $key =&gt; $value) {<br />           $params[$key] = $value;<br />       }<br />   }<br /></pre> ', $art->formatFollowUp(102, 1,$htmlContent, 0));
        $this->assertEqual($txtContent, $art->formatFollowUp(102, 0, $txtContent, 0));
    }
}
