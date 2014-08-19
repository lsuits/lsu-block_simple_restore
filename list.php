<?php
global $CFG;
require_once '../../config.php';
require_once 'lib.php';
require_once $CFG->libdir.'/coursecatlib.php' ;

$courseid = required_param('id', PARAM_INT);
$restore_to = optional_param('restore_to', 0, PARAM_INT);

$name = optional_param('name', null, PARAM_RAW);
$action = optional_param('action', null, PARAM_TEXT);
$file = optional_param('fileid', null, PARAM_RAW);

// Needed for admins, as they need to query the courses
$shortname = optional_param('shortname', null, PARAM_TEXT);

// determine whether archive mode.
$archive_mode = $courseid == SITEID && get_config('simple_restore', 'is_archive_server');

if(!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('no_course', 'block_simple_restore', '', $courseid);
}

// check permissions.
require_login();


// set context and require capabilities depending on archive_mode
if($archive_mode){
    $context = context_system::instance();
    require_capability('block/simple_restore:canrestorearchive', $context);
}else{
    $context = context_course::instance($courseid);

    require_capability('block/simple_restore:canrestore', $context);
}

// Chosen a file
if ($file and $action and $name) {

    // need to get the course name, etc differently when in archive mode.
    if($archive_mode){
        simple_restore_utils::includes();

        // parse the filename for course fullname and category.
        list($fullname, $category) = archive_restore_utils::coursedata_from_filename($file);

        // get a category object,
        if(!$DB->record_exists('course_categories', array('name'=>$category))){
            // create the category if it doesn't exits
            $category = coursecat::create(array('name'=>$category));
        }else{
            // otherwise, just fetch it.
            $category = $DB->get_record('course_categories', array('name'=>$category));
        }

        // prep_restore needs a course and a context.
        $courseid = restore_dbops::create_new_course($fullname, $fullname, $category->id);
        $context = context_course::instance($courseid);

    }

    // move the backup file into place
    $filename = simple_restore_utils::prep_restore($file, $name, $courseid);
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

$system = context_system::instance();

$is_admin = has_capability('moodle/course:create', $system);

if (empty($shortname) and $is_admin) {
    require_once 'list_form.php';

    $form = new list_form();

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    } else if ($data = $form->get_data()) {
        $warn = $OUTPUT->notification(simple_restore_utils::_s('no_filter'));
    }

    $form->set_data(array('id' => $courseid, 'restore_to' =>$restore_to));

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

$data = new stdClass;
$data->restore_to = $restore_to;
$data->courseid = $courseid;
// Admins can filter by shortname
if ($is_admin) {
    $data->shortname = $shortname;
}
$data->lists = array();

events_trigger('simple_restore_backup_list', $data);

$display_list = function($in, $list) {
    echo $list->html;
    return $in || !empty($list->backups);
};

// Obey handled order
usort($data->lists, function($a, $b) {
    if ($a->order == $b->order) return 0;
    return $a->order < $b->order ? -1 : 1;
});

$successful = array_reduce($data->lists, $display_list, false);

if (!$successful) {
    echo $OUTPUT->notification(simple_restore_utils::_s('empty_backups'));
    echo $OUTPUT->continue_button(
        new moodle_url('/course/view.php', array('id' => $courseid))
    );
}

echo $OUTPUT->footer();
