<?php
/*
 * Local development only. Deployed to /opt/tk/ViewLoggerConfig.php by prepare-local-runtime-assets.php
 * when the repo root copy is missing.
 */
$viewLoggerConfigurators = array(
    'MQProducerLogger' => array(
        'Implementation' => 'KLogger',
        'Level'          => 'debug',
        'Directory'      => '/var/log/View',
        'FilePrefix'     => 'MessageQueue_Producer_local_daily_',
    ),
    'MQConsumerLogger' => array(
        'Implementation' => 'KLogger',
        'Level'          => 'info',
        'Directory'      => '/var/log/View',
        'FilePrefix'     => 'MessageQueue_Consumer_local_daily_',
    ),
);
