<?php
namespace block_simple_restore\event;

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class simple_restore_backup_list extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {

        $this->data['crud'] = 'r';
        $this->context = \context_system::instance();
        $this->data['edulevel'] = self::LEVEL_OTHER;
        
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventlist', 'simple_restore');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the comment with id '$this->objectid' from the '$this->component' " .
            "with course module id '$this->contextinstanceid'.";
    }
    

}