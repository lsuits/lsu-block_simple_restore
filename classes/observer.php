<?php

defined('MOODLE_INTERNAL') || die();

// require_once $CFG->dirroot . '/blocks/simple_restore/lib.php';

class block_simple_restore_observer {

    /**
     * Simple restore event
     *
     * @param  \block_simple_restore\event\simple_restore_complete  $event
     */
    public static function simple_restore_complete(\block_simple_restore\event\simple_restore_complete $event) {

		// handle the event here!
    
    }

}