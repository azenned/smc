# smc

You will need to add scripts on your project composer.json and configurations:

    "extra": {
        "smc-enable-apcu": true,
        "smc-enable-services": true
    },
    "scripts": {
        "pre-autoload-dump": "Blueflame\\Composer\\PluginConfiguration::onPreAutoloadDump"
    }

* _smc-enable-apcu_ : add apcu compatiblity layer
* _smc-enable-services_ : enable Drupal SMC service and override cache backend