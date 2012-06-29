<?php

$handlers = array(
    'simple_restore_backup_list' => array(
        'handlerfile' => '/blocks/simple_restore/events.php',
        'handlerfunction' => array('simple_restore_event_handler', 'backup_list'),
        'schedule' => 'instant'
    )
);
