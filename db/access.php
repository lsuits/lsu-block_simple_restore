<?php

$capabilities = array(
    'block/simple_restore:canrestore' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW
        )
    ),
    'block/simple_restore:canrestorearchive' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'user'           => CAP_ALLOW,
        )
    ),
    
    'block/simple_restore:addinstance' => array(
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager'        => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'user'           => CAP_ALLOW,
        ),
    'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
);