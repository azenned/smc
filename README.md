# smc

You will need to add scripts on your project composer.json and configurations:

    "extra": {
        "smc-debug": false,
        "smc-enable-apcu": true,
        "smc-enable-services": true,
        "smc-enable-dashboard": true,
        "smc-dashboard-restrict-access": true,
        "smc-dashboard-user": "admin",
        "smc-dashboard-password": "admin",
        "smc-memsize-apcu": "128",
        "smc-memsize-default": "128"
    },
    "scripts": {
        "pre-autoload-dump": "Blueflame\\Composer\\PluginConfiguration::onPreAutoloadDump"
    }

* _smc-enable-apcu_ : add apcu compatiblity layer
* _smc-enable-services_ : enable Drupal SMC service and override cache backend
* _smc-debug_ : enable debugging messages
* _smc-enable-apcu_ :  add apcu compatiblity layer
* _smc-enable-services_ : enable Drupal SMC service and override cache backend
* _smc-enable-dashboard_ : enable SMC dashboard using /smc_stats endpoint
* _smc-dashboard-restrict-access_ : enable SMC dashboard authentification
* _smc-dashboard-user_ : set SMC dashboard username
* _smc-dashboard-password_ : set SMC dashboard password
* _smc-memsize-apcu_ : set apcu allocated memory size
* _smc-memsize-default_ : set default allocated memory size for each smc instance