<?php

require_once '../../config.php';
require_once 'lib.php';

$contextid = required_param('contextid', PARAM_INT);
$filename = required_param('filename', PARAM_FILE);
$restore_to = optional_param('restore_to', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$loading = optional_param('loading', 0, PARAM_INT);

list($context, $course, $cm) = get_context_info_array($contextid);

require_login($course, null, $cm);
require_capability('block/simple_restore:canrestore', $context);

$blockname = get_string('pluginname', 'block_simple_restore');
$restore_heading = simple_restore_utils::heading($restore_to);

$PAGE->set_url(new moodle_url('/blocks/simple_restore/restore.php',array(
    'contextid' => $contextid)
));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->navbar->add($restore_heading);
$PAGE->set_title($blockname . ': ' . $restore_heading);
$PAGE->set_heading($blockname . ': ' . $restore_heading);

$PAGE->requires->js('/lib/jquery.js');
$PAGE->requires->js('/blocks/simple_restore/js/restore.js');

$restore = new simple_restore($course, $filename, $restore_to);
$header = $course->fullname;

// This conditional returns html content for the ajax reponse
if($confirm and data_submitted()) {
    $restore->execute();

    echo $OUTPUT->notification(
        get_string('restoreexecutionsuccess', 'backup'), 'notifysuccess'
    );
    echo $OUTPUT->continue_button(
        new moodle_url('/course/view.php', array('id' => $course->id))
    );
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($header);

echo '<div id="restore_loading" style="display: none">'.
    $OUTPUT->pix_icon('i/loading', 'Loading').
     '</div>';

$confirm_str = simple_restore_utils::_s('confirm_message',
    '<strong>'.$restore_heading.'</strong>'
);

$confirm_url = new moodle_url('restore.php', array(
    'contextid' => $contextid,
    'restore_to' => $restore_to,
    'confirm' => 1,
    'filename' => $filename
));

$cancel_url = new moodle_url('list.php', array(
    'id' => $course->id,
    'restore_to' => $restore_to
));

echo $OUTPUT->confirm($confirm_str, $confirm_url, $cancel_url);

echo $OUTPUT->footer();
