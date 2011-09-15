<?php

require_once '../../config.php';
require_once 'lib.php';

$courseid = required_param('id', PARAM_INT);
$restore_to = optional_param('restore_to', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_ACTION);
$file = optional_param('fileid', null, PARAM_INT);

// Needed for admins, as they need to query the courses
$shortname = optional_param('shortname', null, PARAM_ACTION);
$sn_filter = optional_param('sn_filter', null, PARAM_ACTION);

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

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->navbar->add($blockname);
$PAGE->set_title($blockname.': '.$heading);
$PAGE->set_heading($blockname.': '.$heading);
$PAGE->set_url('/blocks/simple_restore/list.php',array(
    'id' => $courseid,
    'restore_to' => $restore_to
));

echo $OUTPUT->header();

$system = get_context_instance(CONTEXT_SYSTEM);

$is_admin = has_capability('moodle/course:create', $system);

if(empty($shortname) and $is_admin) {
    require_once $CFG->libdir . '/quick_template.php';
    $options = array(
        'contains' => simple_restore_utils::_s('contains'),
        'equals' => simple_restore_utils::_s('equals'),
        'startswith' => simple_restore_utils::_s('startswith'),
        'endswith' => simple_restore_utils::_s('endswith')
    );
    echo $OUTPUT->heading(simple_restore_utils::_s('adminfilter'));

    // This executes because the admin didn't place anything in there
    if(isset($_POST['submit'])) {
        echo $OUTPUT->notification(simple_restore_utils::_s('no_filter'));
    }

    echo $OUTPUT->box_start();
    quick_render('list.tpl', array('options' => $options));
    echo $OUTPUT->box_end();

    echo $OUTPUT->footer();
    die;
}

$crit = !$is_admin ? simple_restore_utils::backadel_criterion($course) : $shortname;

$backdel = simple_restore_utils::backadel_backups($crit);
$storage = !empty($backdel);

if ($storage) {
    echo $OUTPUT->heading(simple_restore_utils::_s('semester_backups'));
    simple_restore_utils::build_table($backdel, $course, $restore_to);
}

if (!$is_admin) {
    $courses = enrol_get_my_courses();
} else {
    $courses = simple_restore_utils::filter_courses($shortname, $sn_filter);
}

// Map / reduces the course for backups into html tables, and returns whether
// or not each course had backups
$successful = array_reduce($courses, function($in, $course) use ($restore_to) {
    global $DB, $OUTPUT, $restore_to;

    $ctx = get_context_instance(CONTEXT_COURSE, $course->id);

    $backups = $DB->get_records('files', array(
        'component' => 'backup',
        'contextid' => $ctx->id,
        'filearea' => 'course',
        'mimetype' => 'application/vnd.moodle.backup'
    ), 'timemodified DESC');

    // No need to process course if no backups
    if(empty($backups)) return $in || false;

    echo $OUTPUT->heading($course->fullname.' - '. $course->shortname);
    simple_restore_utils::build_table($backups, $course, $restore_to);

    return true;
}, false);

if(!$successful and !$storage) {
    echo $OUTPUT->notification(simple_restore_utils::_s('empty_backups'));
    echo $OUTPUT->continue_button(
        new moodle_url('/course/view.php', array('id' => $courseid))
    );
}

echo $OUTPUT->footer();
