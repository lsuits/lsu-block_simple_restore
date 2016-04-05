<?php

namespace block_simple_restore\event;

defined('MOODLE_INTERNAL') || die();

class block_simple_restore_event_base extends \core\event\base {

    /**
     * Initialize the event
     *
     * @return void
     */
    protected function init() {
        $this->context = \context_system::instance();
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

}