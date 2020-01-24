<?php

namespace MikeWeb\CakeSources;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin for CakePHP Datasources
 * https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server?view=sql-server-2017
 */
class Plugin extends BasePlugin {
    
    /**
     * {@inheritDoc}
     * @see \Cake\Core\BasePlugin::bootstrap()
     */
    public function bootstrap(PluginApplicationInterface $app): void {
        ## TODO: Post load bootstrapping?
    }
}
