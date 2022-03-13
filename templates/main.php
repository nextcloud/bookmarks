<?php
script('bookmarks', 'bookmarks-main');
\OC::$server->getEventDispatcher()->dispatch('\OCP\Collaboration\Resources::loadAdditionalScripts');
?>
<div id="vue-content"></div>
