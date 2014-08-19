<?php

abstract class simple_restore_event_handler {
    // Properly separate area of concerns
    public static function selected_course($data) {
        return self::selected($data);
    }

    public static function selected_user($data) {
        return self::selected($data);
    }

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

    public static function backup_list($data) {
        return (
            self::course_backups($data) and
            self::user_backups($data)
        );
    }

    public static function course_backups($data) {
        if (isset($data->shortname)) {
            $courses = simple_restore_utils::filter_courses($data->shortname);
        } else {
            $courses = enrol_get_my_courses();
        }

        $to_html = function($in, $course) use ($data) {
            global $DB, $OUTPUT;

            $ctx = context_course::instance($course->id);

            $backups = $DB->get_records('files', array(
                'component' => 'backup',
                'contextid' => $ctx->id,
                'filearea' => 'course',
                'mimetype' => 'application/vnd.moodle.backup'
            ), 'timemodified DESC');

            if (empty($backups)) return $in;

            return $in . (
                $OUTPUT->heading($course->shortname) .
                simple_restore_utils::build_table(
                    $backups,
                    'course',
                    $data->courseid,
                    $data->restore_to
                )
            );
        };

        $list = new stdClass;
        $list->html = array_reduce($courses, $to_html, '');
        $list->backups = !empty($list->html);
        $list->order = 100;

        $data->lists[] = $list;

        return true;
    }

    public static function user_backups($data) {
        global $USER, $DB, $PAGE, $OUTPUT;

        $user_context = context_user::instance($USER->id);
        $context = context_course::instance($data->courseid);

        $params = array(
            'component' => 'user',
            'filearea' => 'backup',
            'contextid' => $user_context->id,
        );
        $correct_files = function($file) { return $file->filename != '.'; };
        $backup_files = $DB->get_records('files', $params);

        $params = array(
            'contextid' => $user_context->id,
            'currentcontext' => $context->id,
            'filearea' => 'backup',
            'component' => 'user',
            'returnurl' => $PAGE->url->out(false)
        );

        $str = get_string('managefiles', 'backup');
        $url = new moodle_url('/backup/backupfilesedit.php', $params);

        $list = new stdClass;
        $list->header = get_string('choosefilefromuserbackup', 'backup');
        $list->backups = array_filter($backup_files, $correct_files);
        $list->order = 200;

        $list->html = (
            $OUTPUT->heading($list->header) .
            $OUTPUT->single_button($url, $str, 'post', array('class' => 'center padded'))
        );

        if ($list->backups) {
            $list->html .= simple_restore_utils::build_table(
                $list->backups,
                'user',
                $data->courseid,
                $data->restore_to
            );
        }

        $data->lists[] = $list;

        return true;
    }
}
