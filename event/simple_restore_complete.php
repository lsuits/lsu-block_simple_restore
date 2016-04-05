<?php

namespace block_simple_restore\event;

defined('MOODLE_INTERNAL') || die();

class simple_restore_complete extends \block_simple_restore\event\block_simple_restore_event_base {

    /**
     * Initialize the event
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'r';
    }

}