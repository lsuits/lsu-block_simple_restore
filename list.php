<?php

require_once '../../config.php';
require_once 'lib.php';

$courseid = required_param('id', PARAM_INT);
$restore_to = optional_param('restore_to', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_TEXT);
$file = optional_param('fileid', null, PARAM_RAW);

// Needed for admins, as they need to query the courses
$shortname = optional_param('shortname', null, PARAM_TEXT);

if(!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('no_course', 'block_simple_restore', '', $courseid);
}

require_login();

$context = get_context_instance(CONTEXT_COURSE, $courseid);
require_capability('block/simple_restore:canrestore', $context);

// Chosen a file
if($file and $action) {
    $filename = simple_restore_utils::prep_restore($file, $course->id);
    redirect(new moodle_url('/blocks/simple_restore/restore.php', array(
        'contextid' => $context->id,
        'filename' => $filename,
        'restore_to' => $restore_to
    )));
}

$blockname = get_string('pluginname', 'block_simple_restore');
$heading = simple_restore_utils::heading($restore_to);

$base_url = new moodle_url('/blocks/simple_restore/list.php', array(
    'id' => $courseid, 'restore_to' => $restore_to
));

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->navbar->add($blockname);
$PAGE->set_title($blockname.': '.$heading);
$PAGE->set_heading($blockname.': '.$heading);
$PAGE->set_url($base_url);

$system = get_context_instance(CONTEXT_SYSTEM);

$is_admin = has_capability('moodle/course:create', $system);

if (empty($shortname) and $is_admin) {
    require_once 'list_form.php';

    $form = new list_form();

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    } else if ($data = $form->get_data()) {
        $warn = $OUTPUT->notification(simple_restore_utils::_s('no_filter'));
    }

    $form->set_data(array('id' => $courseid));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(simple_restore_utils::_s('adminfilter'));

    if (!empty($warn)) {
        echo $warn;
    }

    echo $OUTPUT->box_start();
    $form->display();
    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();

if ($is_admin) {
    $crit = simple_restore_utils::backadel_shortname($shortname);
    $courses = simple_restore_utils::filter_courses($shortname);
} else {
    $crit = simple_restore_utils::backadel_criterion($course);
    $courses = enrol_get_my_courses();
}

$backdel = simple_restore_utils::backadel_backups($crit);
$storage = !empty($backdel);

if ($storage) {
    echo $OUTPUT->heading(simple_restore_utils::_s('semester_backups'));
    simple_restore_utils::build_table($backdel, $course, $restore_to);
}

// Map / reduces the course for backups into html tables, and returns whether
// or not each course had backups
$successful = array_reduce($courses, function($in, $c) use ($course, $restore_to) {
    global $DB, $OUTPUT;

    $ctx = get_context_instance(CONTEXT_COURSE, $c->id);

    $backups = $DB->get_records('files', array(
        'component' => 'backup',
        'contextid' => $ctx->id,
        'filearea' => 'course',
        'mimetype' => 'application/vnd.moodle.backup'
    ), 'timemodified DESC');

    // No need to process course if no backups
    if (empty($backups)) return $in || false;

    echo $OUTPUT->heading($c->shortname);
    simple_restore_utils::build_table($backups, $course, $restore_to);

    return true;
}, false);

$str = get_string('choosefilefromuserbackup', 'backup');
echo $OUTPUT->heading($str);

$user_context = get_context_instance(CONTEXT_USER, $USER->id);
$params = array(
    'component' => 'user',
    'filearea' => 'backup',
    'contextid' => $user_context->id,
);
$correct_files = function($file) { return $file->filename != '.'; };
$user_backups = array_filter($DB->get_records('files', $params), $correct_files);

$params = array(
    'contextid' => $user_context->id,
    'currentcontext' => $context->id,
    'filearea' => 'backup',
    'component' => 'user',
    'returnurl' => $base_url->out(false)
);

$str = get_string('managefiles', 'backup');
$url = new moodle_url('/backup/backupfilesedit.php', $params);

echo $OUTPUT->single_button($url, $str, 'post', array('class' => 'center padded'));
if ($user_backups) {
    simple_restore_utils::build_table($user_backups, $course, $restore_to);
}

if (!$successful and !$storage and !$user_backups) {
    echo $OUTPUT->notification(simple_restore_utils::_s('empty_backups'));
    echo $OUTPUT->continue_button(
        new moodle_url('/course/view.php', array('id' => $courseid))
    );
}

echo $OUTPUT->footer();
