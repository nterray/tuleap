<?php

require_once 'common/plugin/PluginDescriptor.class.php';

/**
 * BrowserIDPluginDescriptor
 */
class BrowserIDPluginDescriptor extends PluginDescriptor {

    function __construct() {
        parent::__construct($GLOBALS['Language']->getText('plugin_browserid', 'descriptor_name'), false, $GLOBALS['Language']->getText('plugin_browserid', 'descriptor_description'));
        $this->setVersionFromFile(dirname(__FILE__).'/../VERSION');
    }
}
?>