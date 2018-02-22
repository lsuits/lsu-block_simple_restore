<?php

abstract class simple_restore_utils {
    // We don't need the includes on every request.
    public static function includes() {
        global $CFG;
        require_once $CFG->dirroot.'/backup/util/includes/restore_includes.php';
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
        switch($restore_to){
            case 0:
                return get_string('delete_restore', 'block_simple_restore');
            case 1:
                return get_string('restore_course', 'block_simple_restore');
            case 2:
                return get_string('restore_course_archive', 'block_simple_restore');
        }
    }

    public static function prep_semester_backup_restore($fileid, $name, $courseid) {
        global $USER, $CFG;

        // Get the includes
        self::includes();

        if (empty($fileid) || empty($courseid)) {
           throw new Exception(get_string('no_arguments', 'block_simple_restore'));
        }

        $filename = restore_controller::get_tempdir_name($courseid, $USER->id);
        $pathname = $CFG->tempdir . '/backup/' . $filename;

        // restore the file for the course to the path
        $filename = self::simple_restore_selected($name, $fileid, $courseid, $pathname);

        if (empty($filename)) {
            throw new Exception(get_string('no_file', 'block_simple_restore'));
        }

        return $filename;
    }

    /**
     * Restores the given fileid for the given courseid to the path and returns the filename
     * 
     * @param  string  $name       user|course
     * @param  int     $fileid
     * @param  int     $course_id
     * @param  string  $to_path
     * @return string
     */
    public static function simple_restore_selected($name, $fileid, $courseid, $to_path) {
        global $DB, $CFG;

        $backup = $DB->get_record('files', array('id' => $fileid));

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
            has_capability('block/simple_restore:canrestore', context_course::instance($courseid)),
            false,
            true
        );

        $fileinfo->copy_to_pathname($to_path);

        return $backup->filename;
    }

    /**
     * Returns an array of course and user backup list objects which contain: html, success boolean, display order
     * 
     * @param  int     $courseid
     * @param  string  $restore_to
     * @param  string  $shortname
     * @return array
     */
    public static function get_backup_list_objects($courseid, $restore_to, $shortname = null) {
        $list_objects = array();
        $list_objects[] = self::get_semester_backup_list_object($courseid, $restore_to, $shortname);
        $list_objects[] = self::get_course_backup_list_object($courseid, $restore_to, $shortname);
        $list_objects[] = self::get_user_course_backup_list_object($courseid, $restore_to);

        return $list_objects;
    }

    /**
     * Returns a "list object" containing semester course backup list data for the given courseid
     *
     * The list object contains: an html string, a success boolean, and a display order
     * 
     * @param  int     $courseid
     * @param  string  $restore_to
     * @param  string  $shortname
     * @return object
     */
    
    public static function get_semester_backup_list_object($courseid, $restore_to, $shortname = null) {
        global $DB, $OUTPUT;

        if (isset($shortname)) {
            $search = self::search_shortname($shortname);
        } else {
            $course = $DB->get_record('course', array('id' => $courseid));
            $search = self::get_search_criterion($course);
        }

        $list = new stdClass;
        $list->header = get_string('semester_backups', 'block_simple_restore');
        $list->backups = self::get_semester_backups($search);
        $list->order = 10;
        $list->html = '';

        if (!empty($list->backups)) {
            $list->html = $OUTPUT->heading($list->header);
            $list->html .= simple_restore_utils::build_table(
                $list->backups,
                'backadel',
                $courseid,
                $restore_to
            );
        }

        return $list;
    }

    /**
     * Returns a "list object" containing course backup list data for the given courseid
     *
     * The list object contains: an html string, a success boolean, and a display order
     * 
     * @param  int     $courseid
     * @param  string  $restore_to
     * @param  string  $shortname
     * @return object
     */
    public static function get_course_backup_list_object($courseid, $restore_to, $shortname = null) {
        if (isset($shortname)) {
            $courses = self::filter_courses($shortname);
        } else {
            $courses = enrol_get_my_courses();
        }

        $to_html = function($in, $course) use ($restore_to, $courseid) {
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
                self::build_table(
                    $backups,
                    'course',
                    $courseid,
                    $restore_to
                )
            );
        };

        $list = new stdClass;
        $list->html = array_reduce($courses, $to_html, '');
        $list->backups = !empty($list->html);
        $list->order = 100;

        return $list;
    }

    /**
     * Returns a "list object" containing user course backup list data for the given courseid
     *
     * The list object contains: an html string, a success boolean, and a display order
     * 
     * @param  int     $courseid
     * @param  string  $restore_to
     * @return object
     */
    public static function get_user_course_backup_list_object($courseid, $restore_to) {
        global $USER, $DB, $PAGE, $OUTPUT;

        $user_context = context_user::instance($USER->id);
        $context = context_course::instance($courseid);

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
            $list->html .= self::build_table(
                $list->backups,
                'user',
                $courseid,
                $restore_to
            );
        }

        return $list;
    }

    /**
     * Search for matching shortname
     * 
     * @param  string  $shortname
     * @return string
     */
    public static function search_shortname($shortname) {
        if (preg_match('/\s/', $shortname)) {
            $matchers = array('/\s/', '/\//');

            return preg_replace($matchers, '-', $shortname);
        }

        return $shortname;
    }

    /**
     * Get search criterion
     * 
     * @param  object  $course
     * @return string
     */
    public static function get_search_criterion($course) {
        global $USER;

        $crit = get_config('block_simple_restore', 'suffix');

        if (empty($crit)) {
            return "";
        }

        $search = $crit == 'username' ? '_' . $USER->username : $course->{$crit};
        return "{$search}[_\.]";
    }

    /**
     * Gets semester backups
     * 
     * @param  string  $search
     * @return array
     */
    public static function get_semester_backups($search) {
        global $CFG;

        $backadel_path = get_config('block_simple_restore', 'path');

        if (empty($backadel_path)) {
            return array();
        }

        $backadel_path = "$CFG->dataroot$backadel_path";

        $by_search = function ($file) use ($search) {
            return preg_match("/{$search}/i", $file);
        };

        $to_backup = function ($file) use ($backadel_path) {
            $backadel = new stdClass;
            $backadel->id = $file;
            $backadel->filename = $file;
            $backadel->filesize = filesize($backadel_path . $file);
            $backadel->timemodified = filemtime($backadel_path . $file);

            return $backadel;
        };

        $potentials = array_filter(scandir($backadel_path), $by_search);

        return array_map($to_backup, $potentials);
    }

}

class archive_restore_utils extends simple_restore_utils {
    /**
     * Get course name and category from filename.
     * 
     * NB: this function expects files from backadel whose names
     * begin as 'backadel-', 
     * for example: 
     * backup-moodle2-course-2-2014_spring_tst2_2011_for_instructor_four-20140407-1539.mbz
     * 
     * @param string $filename 
     */
    public static function coursedata_from_filename($filename){
        $prefix = 'backadel';
        if (substr($filename, 0, strlen($prefix)) == $prefix) {
            $filename = substr($filename, strlen($prefix)+1);
        }else{
            // @todo - do something better than throw an error if it isn't a backadel file.
            // Consider restricting the choice of files in the first place!
            throw new exception("Archive Restore does not support filenames other than 'backadel-*'");
        }
        
        $chunks     = explode('_', $filename);
        $meta       = $chunks[0];
        $metachunks = explode('-', $meta);
        $fullname   = implode(' ', $metachunks);
        $category   = $metachunks[2];
        
        return array($fullname, $category);
    }
}

class simple_restore {
    var $userid;
    var $course;
    var $filename;
    var $restore_to;

    function __construct($course, $filename, $restore_to = 0) {
        if(empty($course)) {
            throw new Exception(get_string('no_context', 'block_simple_restore'));
        }

        if(empty($filename)) {
            throw new Exception(get_string('no_file', 'block_simple_restore'));
        }

        global $USER;

        $this->userid = $USER->id;
        $this->course = $course;
        $this->context = context_course::instance($course->id);
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
        $_POST['sesskey']   = sesskey();
        $_POST['filepath']  = $this->rip_value($restore, 'filepath');
        $_POST['target']    = $this->restore_to;
        $_POST['targetid']  = $this->course->id;

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
                $admin_setting = get_config('block_simple_restore', $admin_setting_key);
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
        if($this->restore_to == 2 && get_config('block_simple_restore', 'is_archive_server')){
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

    /**
     * Create a new course from the selected backup file.
     * 
     * This method is inspired by @seecore_course_external::duplicate_course 
     * found in /course/externallib.php.
     * 
     * @global type $CFG
     * @global type $DB
     * @global type $USER
     * @return boolean
     * @throws moodle_exception
     */
    public function archive_mode_execute() {
        global $CFG, $DB, $USER;
        require_once $CFG->dirroot.'/enrol/manual/lib.php';
        simple_restore_utils::includes();
        
        // enrol the current user as teacher.
        $plugin       = new enrol_manual_plugin();
        $plugin->add_instance($this->course);

        $instances    = enrol_get_instances($this->course->id, true);
        $isntance     = null;
        foreach($instances as $enrol_instance){
            if($enrol_instance->enrol == 'manual'){
                $instance = $enrol_instance;
                break;
            }
        }

        $roleid = $DB->get_field('role', 'id', array('shortname'=>'editingteacher'));
        $plugin->enrol_user($instance, $USER->id, $roleid);

        // setup tempdir for the restore process
        $extractname = restore_controller::get_tempdir_name($this->course->id, $USER->id);
        $extractpath = $CFG->tempdir . '/backup/' . $extractname;
        $filepath    = $CFG->tempdir.'/backup/'.$this->filename;

        if(!has_capability('moodle/restore:userinfo', $this->context, $USER->id)){
            // delete, abort, etc.
            echo "deleting temporary course files and materials.";
            fulldelete($filepath);
            delete_course($this->course);
            fix_course_sortorder(); //update course count in catagories
            // "In order to restore Archived courses, this role must be granted the capability moodle/restore:userinfo - Ask your administrator"
            throw new restore_controller_exception("no userinfo cap");
        }
        
        // .zip needs to be unzipped.
        if (!file_exists($filepath. "/moodle_backup.xml")) {
            $fb = get_file_packer('application/vnd.moodle.backup');
            $fb->extract_to_pathname("$CFG->tempdir/backup/".$this->filename, $extractpath);
        }

        $rc = new restore_controller($extractname, $this->course->id,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id, backup::TARGET_NEW_COURSE);
        
        // iterate through our settings and make sure they are reflected in the restore plan.
        $config_settings = array_values($this->get_settings());

        foreach($config_settings as $config) {
            if($rc->get_plan()->setting_exists($config->name)){
                $setting = $rc->get_plan()->get_setting($config->name);
                if ($setting->get_status() == backup_setting::NOT_LOCKED) {
                    $setting->set_value($config->value);
                }
            }
        }

        // setup restore process and ensure there are no errors
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

        // get the coreect course name; prevents dupe names.
        list($this->course->fullname, $this->course->shortname) = 
                restore_dbops::calculate_course_names(
                        $this->course->id, 
                        $this->course->fullname, 
                        $this->course->shortname
                        );

        $rc->execute_plan();
        $rc->destroy();

        // Set shortname and fullname back, ensure visibility.
        $this->course->visible = 1;
        $DB->update_record('course', $this->course);

        // clean up after ourselves.
        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($filepath);
        }

        return true;
    }
    
    private function get_settings(){
        global $DB;
        $settings = $DB->get_records('config_plugins', array('plugin'=>'block_simple_restore'), null, 'id,name,value');
        return $settings;
    }

}
