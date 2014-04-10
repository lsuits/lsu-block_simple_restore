<?php

require_once $CFG->dirroot . '/blocks/simple_restore/lib.php';

class block_simple_restore extends block_list {
    function init() {
        $this->title = simple_restore_utils::_s('pluginname');
    }

    function applicable_formats() {
        return array('site' => true, 'course' => true, 'my' => false);
    }
    
    function has_config(){
        return true;
    }
    
    function get_content() {
        global $CFG, $COURSE, $OUTPUT;
        if($this->content !== NULL) {
            return $this->content;
        }

        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        if(!simple_restore_utils::permission('canrestore', $context)) {
            return $this->content;
        }

        if($COURSE->id != SITEID){
            $content = $this->get_course_content();
        }else{
            $content = $this->get_site_content();
        }

        $content->footer = '';
        $this->content = $content;
        return $this->content;
    }

    /**
     * Builds the content object apropriate to course contexts.
     * 
     * @global type $COURSE
     * @global type $OUTPUT
     * @return \stdclass
     */
    private function get_course_content(){
        global $COURSE, $OUTPUT;
        $content = new stdclass;

        $import_str = simple_restore_utils::_s('restore_course');
        $delete_str = simple_restore_utils::_s('delete_restore');

        $content->items = array(
            $this->gen_link(1, $import_str),
            $this->gen_link(0, $delete_str)
        );

        $params = array('class' => 'icon');

        $content->icons = array(
            $OUTPUT->pix_icon('import', $import_str, 'block_simple_restore', $params),
            $OUTPUT->pix_icon('overwrite', $delete_str, 'block_simple_restore', $params)
        );

        return $content;
    }

    /**
     * Build the content object appropriate to the SITE context.
     * 
     * @global type $COURSE
     * @global type $OUTPUT
     * @return \stdclass
     */
    private function get_site_content(){
        global $COURSE, $OUTPUT;
        $content = new stdclass;

        $archive_str = simple_restore_utils::_s('archive_restore');

        $content->items = array(
            $this->gen_link(2, $archive_str),
        );
        // @todo add icon here
        $content->icons = array();
        return $content;
    }
    
    /**
     * helper fn for generating the block links.
     */
    private function gen_link($restore_to, $text) {
        global $COURSE;
            return html_writer::link(
                new moodle_url('/blocks/simple_restore/list.php', array(
                    'id' => $COURSE->id,
                    'restore_to' => $restore_to
                )), $text
            );
    }
} 
