<?php

$handlers = array(
    
    // this has been converted to a static function in lib.php, we should probably look elsewhere to make sure this is ok (chad)
    // 
    // 'simple_restore_backup_list' => array(
    //     'handlerfile' => '/blocks/simple_restore/events.php',
    //     'handlerfunction' => array('simple_restore_event_handler', 'backup_list'),
    //     'schedule' => 'instant'
    // ),

    // 'simple_restore_selected_user' => array(
    //    'handlerfile' => '/blocks/simple_restore/events.php',
    //    'handlerfunction' => array('simple_restore_event_handler', 'selected_user'),
    //    'schedule' => 'instant'
    // ),

    'simple_restore_selected_course' => array(
        'handlerfile' => '/blocks/simple_restore/events.php',
        'handlerfunction' => array('simple_restore_event_handler', 'selected_course'),
        'schedule' => 'instant'
    )
);

$observers = array(
    
     array(
         'eventname' => '\block_simple_restore\event\simple_restore_selected_user',
         'callback'  => 'block_simple_restore_observer::simple_restore_selected_user',
    ),

    array(
        'eventname' => '\block_simple_restore\event\simple_restore_selected_course',
        'callback'  => 'block_simple_restore\event\simple_restore_selected_course',
    ),

);