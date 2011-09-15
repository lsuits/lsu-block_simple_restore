<?php

require_once $CFG->dirroot . '/blocks/simple_restore/lib.php';

class block_simple_restore extends block_list {
    function init() {
        $this->title = simple_restore_utils::_s('pluginname');
    }

    function applicable_formats() {
        return array('site' => false, 'course' => true, 'my' => false);
    }

    function get_content() {
        global $COURSE, $OUTPUT;
        if($this->content !== NULL) {
            return $this->content;
        }

        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        if(!simple_restore_utils::permission('canrestore', $context)) {
            return $this->content;
        }

        $content = new stdclass;

        $import_str = simple_restore_utils::_s('restore_course');
        $delete_str = simple_restore_utils::_s('delete_restore');

        $gen_link = function ($restore_to, $text) use ($COURSE) {
            return html_writer::link(
                new moodle_url('/blocks/simple_restore/list.php', array(
                    'id' => $COURSE->id,
                    'restore_to' => $restore_to
                )), $text
            );
        };

        $content->items = array(
            $gen_link(1, $import_str),
            $gen_link(0, $delete_str)
        );

        $content->icons = array(
            $OUTPUT->pix_icon('i/restore', $import_str),
            $OUTPUT->pix_icon('i/backup', $delete_str)
        );

        $content->footer = '';
        $this->content = $content;
        return $this->content;
    }
} 
