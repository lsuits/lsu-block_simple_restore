<?php

abstract class simple_restore_utils {
    // We don't need the includes on every request.
    public static function includes() {
        global $CFG;
        require_once $CFG->dirroot.'/backup/util/includes/restore_includes.php';
    }

    public static function permission($cap, $context) {
        return has_capability("block/simple_restore:{$cap}", $context);
    }

    public static function _s($name, $a=null) {
        return get_string($name, 'block_simple_restore', $a);
    }

    public static function build_table($backups, $name, $courseid, $restore_to) {
        $table = new html_table();
        $table->head = array(
            get_string('name'),
            get_string('size'),
            get_string('modified')
        );

        $to_row = function($backup) use ($name, $courseid, $restore_to) {
            $link = html_writer::link(
                new moodle_url('/blocks/simple_restore/list.php', array(
                    'id' => $courseid,
                    'name' => $name,
                    'action' => 'choosefile',
                    'restore_to' => $restore_to,
                    'fileid' => $backup->id
                )), $backup->filename);
            $name = new html_table_cell($link);
            $size = new html_table_cell(display_size($backup->filesize));
            $modified = new html_table_cell(date('d M Y, h:i:s A',
                                            $backup->timemodified));
            return new html_table_row(array($name, $size, $modified));
        };

        $table->data = array_map($to_row, $backups);

        return html_writer::table($table);
    }

    public static function filter_courses($shortname) {
        global $DB;

        $safe_shortname = addslashes($shortname);

        $select = "shortname LIKE '%{$safe_shortname}%'";

        return $DB->get_records_select('course', $select);
    }

    public static function heading($restore_to) {
        return $restore_to == 0 ?
                simple_restore_utils::_s('delete_restore') :
                simple_restore_utils::_s('restore_course');
    }

    public static function prep_restore($fileid, $name, $courseid) {
        global $USER, $CFG;

        // Get the includes
        simple_restore_utils::includes();

        if (empty($fileid) || empty($courseid)) {
           throw new Exception(simple_restore_utils::_s('no_arguments'));
        }

        $filename = restore_controller::get_tempdir_name($courseid, $USER->id);
        $pathname = $CFG->tempdir . '/backup/' . $filename;

        $data = new stdClass;
        $data->userid = $USER->id;
        $data->courseid = $courseid;
        $data->fileid = $fileid;
        $data->to_path = $pathname;

        // Handlers do the correct copying
        events_trigger('simple_restore_selected_' . $name , $data);

        if (empty($data->filename)) {
            throw new Exception(simple_restore_utils::_s('no_file'));
        }

        return $filename;
    }
    
    /**
     * Get course name and category and owner? from filename.
     * @param string $filename 
     */
    public static function coursedata_from_filename($filename){
        $prefix = 'backadel';
        if (substr($filename, 0, strlen($prefix)) == $prefix) {
            $filename = substr($filename, strlen($prefix)+1);
        }
        
        $chunks = explode('_', $filename);
        $meta = $chunks[0];
        
        $metachunks = explode('-', $meta);
        
        $fullname = implode(' ', $metachunks);

        
        $category = $metachunks[2];
        
        return array($fullname, $category);
    }
}

class simple_restore {
    var $userid;
    var $course;
    var $filename;
    var $restore_to;

    function __construct($course, $filename, $restore_to = 0) {
        if(empty($course))
            throw new Exception(simple_restore_utils::_s('no_context'));

        if(empty($filename))
            throw new Exception(simple_restore_utils::_s('no_file'));

        global $USER;

        $this->userid = $USER->id;
        $this->course = $course;
        $this->context = get_context_instance(CONTEXT_COURSE, $course->id);
        $this->filename = $filename;
        $this->restore_to = $restore_to;
    }

    private function process_confirm() {
        $restore = restore_ui::engage_independent_stage(
            restore_ui::STAGE_CONFIRM, $this->context->id
        );
        $restore->process();

        return $restore;
    }

    private function process_destination($restore) {
        $_POST['sesskey'] = sesskey();
        $_POST['filepath'] = $this->rip_value($restore, 'filepath');
        $_POST['target'] = $this->restore_to;

        // @todo find out why this needs to be set differently
        if($this->restore_to == 2){
            $_POST['targetid'] = $this->course->category;
        }else{
            $_POST['targetid'] = $this->course->id;
        }

        $rtn = restore_ui::engage_independent_stage(
            restore_ui::STAGE_DESTINATION, $this->context->id
        );
        $rtn->process();

        return $rtn;
    }

    private function process_schema($rc) {

        // File dependencies
        $file_dependencies = array(
            'block' => 1, 'comments' => 1, 'filters' => 1
        );

        $_POST['stage'] = restore_ui::STAGE_SCHEMA;
        $restore = new restore_ui($rc, array('contextid' => $this->context->id));

        // Forge posts
        $_POST['restore'] = $restore->get_restoreid();

        // get all tasks from the UI object through reflection.
        $tasks = $this->rip_ui($restore)->get_tasks();
        foreach ($tasks as $task) {
            $settings = $task->get_settings();
            foreach ($settings as $setting) {
                $setting_name = $setting->get_name();

                if (preg_match('/(.+)_(\d+)_(.+)/', $setting_name, $matches)) {
                    $module = $matches[1];
                    $type = $matches[3];
                    $admin_setting_key = $module.'_'.$type;
                } else {
                    $admin_setting_key = $setting_name;
                }
                $admin_setting = get_config('simple_restore', $admin_setting_key);
                if (!is_numeric($admin_setting)) {
                    continue;
                }

                if ($admin_setting and isset($file_dependencies[$setting_name])) {
                    $basepath = $task->get_taskbasepath();
                    if (!file_exists("$basepath/$setting_name.xml")) {
                        continue;
                    }
                }
                // Set admin value
                // Some settings may be locked by permission
                if ($setting->get_status() == base_setting::NOT_LOCKED) {
                    $setting->set_value($admin_setting);
                }
            }
        }

        $restore->process();
        $restore->save_controller();
        return $restore;
    }

    private function rip_value($restore, $property) {
        $reflector = new ReflectionObject($restore);
        $prop = $reflector->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($restore);
    }

    private function rip_stage($restore) {
        return $this->rip_value($restore, 'stage');
    }

    private function rip_ui($restore) {
        return $this->rip_value($this->rip_stage($restore), 'ui');
    }

    private function process_final($restore) {
        $_POST['stage'] = restore_ui::STAGE_PROCESS;
        $rc = restore_ui::load_controller($restore->get_restoreid());
        $final = new restore_ui($rc, array('contextid' => $this->context->id));
        $final->process();
        $final->execute();
        $final->destroy();
        unset($final);
    }

    public function execute() {
        simple_restore_utils::includes();

        //archive mode
        if($this->restore_to == 2 && get_config('simple_restore', 'is_archive_server')){
            return $this->archive_mode_execute();
        }
        // Confirmed ... process destination
        $confirmed = $this->process_destination($this->process_confirm());

        // Setting up controller ... tmp tables
        $rc = new restore_controller(
            $confirmed->get_filepath(),
            $confirmed->get_course_id(),
            backup::INTERACTIVE_YES,
            backup::MODE_GENERAL,
            $this->userid,
            $confirmed->get_target()
        );

        if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
            $rc->convert();
        }

        // Probably good to do this
        unset($confirmed);
        $this->process_final($this->process_schema($rc));

        // Restore blocks
        if ($this->restore_to == 0) {
            blocks_delete_all_for_context($this->context->id);
            blocks_add_default_course_blocks($this->course);
        }

        // It's important to pass the previous course's config
        $course_settings = array(
            'restore_to' => $this->restore_to,
            'course' => $this->course
        );

        events_trigger('simple_restore_complete', array(
            'userid' => $this->userid,
            'course_settings' => $course_settings
        ));

        return true;
    }

    public function archive_mode_execute() {
        global $CFG, $DB, $USER;

        simple_restore_utils::includes();

        $extractname = restore_controller::get_tempdir_name($this->course->id, $USER->id);
        $extractpath = $CFG->tempdir . '/backup/' . $extractname;
        $filepath    = $CFG->tempdir.'/backup/'.$this->filename;

        if (!file_exists($filepath. "/moodle_backup.xml")) {
            $fb = get_file_packer();
            $fb->extract_to_pathname("$CFG->tempdir/backup/".$this->filename, $extractpath);
        }

        $rc = new restore_controller($extractname, $this->course->id,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id, backup::TARGET_NEW_COURSE);
        
        // need the enrol_migrate setting, otherwise no enrollments!
        $migrate_setting = new stdClass();
        $migrate_setting->name = 'enrol_migratetomanual';
        $migrate_setting->value = 1;
        $config_settings = array_values($this->get_settings());
        array_unshift($config_settings, $migrate_setting);

        foreach($config_settings as $config) {
            if($rc->get_plan()->setting_exists($config->name)){
                $setting = $rc->get_plan()->get_setting($config->name);
                if ($setting->get_status() == backup_setting::NOT_LOCKED) {
                    $setting->set_value($config->value);
                }
            }
        }

        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($filepath);
                }

                $errorinfo = '';

                foreach ($precheckresults['errors'] as $error) {
                    $errorinfo .= $error;
                }

                if (array_key_exists('warnings', $precheckresults)) {
                    foreach ($precheckresults['warnings'] as $warning) {
                        $errorinfo .= $warning;
                    }
                }

                throw new moodle_exception('backupprecheckerrors', 'webservice', '', $errorinfo);
            }
        }
        
        $course = $DB->get_record('course', array('id' => $this->course->id), '*', MUST_EXIST);
        $course->visible   = 1;
        list($course->fullname, $course->shortname) = restore_dbops::calculate_course_names($course->id, $course->fullname, $course->shortname);

        $rc->execute_plan();
        $rc->destroy();

        // Set shortname and fullname back.
        $DB->update_record('course', $course);

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($filepath);
        }

        // @TODO
        // Delete the course backup file created by this WebService. Originally located in the course backups area.
//        $file->delete();

//        return array('id' => $course->id, 'shortname' => $course->shortname);
        return true;
    }
    
    private function get_settings(){
        global $DB;
        $settings = $DB->get_records('config_plugins', array('plugin'=>'simple_restore'), null, 'id,name,value');
        return $settings;
    }

}
