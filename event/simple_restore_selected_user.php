<?php

namespace block_simple_restore\event;

defined('MOODLE_INTERNAL') || die();

class simple_restore_selected_user extends \block_simple_restore\event\block_simple_restore_event_base {

    /**
     * Initialize the event
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'r';
    }

    // Properly separate area of concerns
    public static function selected_course($data) {
        return self::selected($data);
    }
    // this is called - s1
    public static function selected_user($data) {
        return self::selected($data);
    }
    // s2
    private static function selected($data) {
        global $DB, $CFG;

        $backup = $DB->get_record('files', array('id' => $data->fileid));

        if (empty($backup)) {
            return true;
        }

        $fs = get_file_storage();
        $browser = get_file_browser();
        $filecontext = context::instance_by_id($backup->contextid);
        $storedfile = $fs->get_file(
            $filecontext->id,
            $backup->component,
            $backup->filearea,
            $backup->itemid,
            $backup->filepath,
            $backup->filename
        );

        $fileinfo = new file_info_stored(
            $browser,
            $filecontext,
            $storedfile,
            $CFG->wwwroot.'/pluginfile.php',
            '',
            false,
            simple_restore_utils::permission(
                'canrestore',
                context_course::instance($data->courseid)
            ),
            false,
            true
        );

        $fileinfo->copy_to_pathname($data->to_path);

        $data->filename = $backup->filename;

        return true;
    }

}
