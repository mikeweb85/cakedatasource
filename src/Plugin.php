<?php

namespace SqlServer;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Datasource\ConnectionManager;

/**
 * Plugin for SqlServer
 * https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server?view=sql-server-2017
 */
class Plugin extends BasePlugin {
    
    /**
     * {@inheritDoc}
     * @see \Cake\Core\BasePlugin::bootstrap()
     */
    public function bootstrap(PluginApplicationInterface $app) {
        putenv('ODBCSYSINI='.env('ODBCSYSINI', '/etc'));
        putenv('ODBCINI='.env('ODBCINI', '/etc/odbc.ini')); 
        
        ConnectionManager::setDsnClassMap([
            'sqldblib'      => 'SqlServer\Database\Driver\Dblib',
            'sqlodbc'       => 'SqlServer\Database\Driver\Odbc',
        ]);
    }
}
