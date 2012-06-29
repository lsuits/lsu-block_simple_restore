<?php

abstract class simple_restore_event_handler {
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

            $ctx = get_context_instance(CONTEXT_COURSE, $course->id);

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

        $user_context = get_context_instance(CONTEXT_USER, $USER->id);
        $context = get_context_instance(CONTEXT_COURSE, $data->courseid);

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
                $data->courseid,
                $data->restore_to
            );
        }

        $data->lists[] = $list;

        return true;
    }
}
