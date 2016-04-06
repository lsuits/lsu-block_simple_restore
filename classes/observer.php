<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/blocks/simple_restore/lib.php';

class block_simple_restore_observer {

    /**
     * Simple Restore event
     *
     * @param  \block_simple_restore\event\simple_restore_selected_user $event
     * @param  int  other['userid']
     * @param  int  other['restore_to'] 0,1,2
     * @param  int  other['courseid']
     */
    public static function simple_restore_complete(\block_simple_restore\event\simple_restore_complete $event) {

        try {
            global $DB, $CFG, $USER;
            require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

            $sectionid = $event->other['ues_section_id'];
            $restore_to = $event->other['restore_to'];
            $old_course = get_course($event->other['courseid']);

            $skip = array(
                'id', 'category', 'sortorder',
                'sectioncache', 'modinfo', 'newsitems'
            );

            $course = $DB->get_record('course', array('id' => $old_course->id));

            $reset_grades = cps_setting::get(array(
                'name' => 'user_grade_restore',
                'userid' => $USER->id
            ));

            // Defaults to reset grade items
            if (empty($reset_grades)) {
                $reset_grades = new stdClass;
                $reset_grades->value = 1;
            }

            // Maintain the correct config
            foreach (get_object_vars($old_course) as $key => $value) {
                if (in_array($key, $skip)) {
                    continue;
                }

                $course->$key = $value;
            }

            $DB->update_record('course', $course);

            if ($reset_grades->value == 1) {
                require_once $CFG->libdir . '/gradelib.php';

                $items = grade_item::fetch_all(array('courseid' => $course->id));
                foreach ($items as $item) {
                    $item->plusfactor = 0.00000;
                    $item->multfactor = 1.00000;
                    $item->update();
                }

                grade_regrade_final_grades($course->id);
            }

            // This is an import, ignore
            if ($restore_to == 1) {
                return true;
            }

            $keep_enrollments = (bool) get_config('simple_restore', 'keep_roles_and_enrolments');
            $keep_groups = (bool) get_config('simple_restore', 'keep_groups_and_groupings');

            // No need to re-enroll
            if ($keep_groups and $keep_enrollments) {
                $enrol_instances = $DB->get_records('enrol', array(
                    'courseid' => $old_course->id,
                    'enrol' => 'ues'
                ));

                // Cleanup old instances
                $ues = enrol_get_plugin('ues');

                foreach (array_slice($enrol_instances, 1) as $instance) {
                    $ues->delete_instance($instance);
                }

            } else {
                $sections = ues_section::from_course($course);

                // Nothing to do
                if (empty($sections)) {
                    return true;
                }

                // Rebuild enrollment
                ues::enrollUsers(ues_section::from_course($course));
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }
}