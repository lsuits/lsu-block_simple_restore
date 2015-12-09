<?php

$handlers = array(
    'simple_restore_backup_list' => array(
        'handlerfile' => '/blocks/simple_restore/events.php',
        'handlerfunction' => array('simple_restore_event_handler', 'backup_list'),
        'schedule' => 'instant'
    ),

    'simple_restore_selected_user' => array(
        'handlerfile' => '/blocks/simple_restore/events.php',
        'handlerfunction' => array('simple_restore_event_handler', 'selected_user'),
        'schedule' => 'instant'
    ),

    'simple_restore_selected_course' => array(
        'handlerfile' => '/blocks/simple_restore/events.php',
        'handlerfunction' => array('simple_restore_event_handler', 'selected_course'),
        'schedule' => 'instant'
    )
);
$observers = array(
    array(
        'eventname' => '\block_simple_restore\simple_restore_backup_list',
        'callback'  => 'block_simple_restore_observer::simple_restore_back_list()',
        'internal'  => true, 
        'priority'  => 1000,
    )
);