<?php

require_once 'common/plugin/PluginInfo.class.php';
require_once 'BrowserIDPluginDescriptor.class.php';


/**
 * BrowserIDPluginInfo
 */
class BrowserIDPluginInfo extends PluginInfo {

    function __construct($plugin) {
        parent::__construct($plugin);
        $this->setPluginDescriptor(new BrowserIDPluginDescriptor());
    }
}
?>