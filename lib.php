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

    public static function build_table($backups, $course, $restore_to) {
        $table = new html_table();
        $table->head = array(
            get_string('name'),
            get_string('size'),
            get_string('modified')
        );

        $table->data = array_map(function($backup) use ($course, $restore_to) {
            $link = html_writer::link(
                new moodle_url('/blocks/simple_restore/list.php', array(
                    'id' => $course->id,
                    'action' => 'choosefile',
                    'restore_to' => $restore_to,
                    'fileid' => $backup->id
                )), $backup->filename); 
            $name = new html_table_cell($link);
            $size = new html_table_cell(display_size($backup->filesize));
            $modified = new html_table_cell(date('d M Y, h:i:s A', 
                                            $backup->timemodified));
            return new html_table_row(array($name, $size, $modified));
        }, $backups);
        echo html_writer::table($table);
    }

    public static function filter_courses($shortname, $filter) {
        global $DB;

        $filter_by = function ($field) use ($shortname, $filter) {
            switch ($filter) {
                case "contains": return "{$field} LIKE '%{$shortname}%'";
                case "equals": return "{$field} == '{$shortname}'";
                case "startswith" : return "{$field} LIKE '{$shortname}%'";
                case "endswith": return "{$field} LIKE '%{$shortname}'";
            }
        };

        return $DB->get_records_select('course', $filter_by('shortname'));
    }

    public static function heading($restore_to) {
        return $restore_to == 0 ?
                simple_restore_utils::_s('delete_restore') :
                simple_restore_utils::_s('restore_course');
    }

    public static function backadel_shortname($shortname) {
        if (preg_match('/\s/', $shortname)) {
            $matchers = array('/\s/', '/\//');

            return preg_replace($matchers, '-', $shortname);
        }

        return $shortname;
    }

    public static function backadel_criterion($course) {
        global $USER;

        $crit = get_config('block_backadel', 'suffix');

        if (empty($crit)) {
            return "";
        }

        return $crit == 'username' ? '_' . $USER->username : $course->{$crit};
    }

    public static function backadel_backups($search) {
        global $CFG;

        $backadel_path = get_config('block_backadel', 'path');

        if (empty($backadel_path)) {
            return array();
        }

        $backadel_path = "$CFG->dataroot$backadel_path";

        $by_search = function ($file) use ($search) {
            return preg_match("/{$search}[_.]/", $file);
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

    public static function prep_restore($fileid, $courseid) {
        global $USER, $CFG;

        // Get the includes
        simple_restore_utils::includes();

        if(empty($fileid) || empty($courseid)) {
           throw new Exception(simple_restore_utils::_s('no_arguments'));
        }

        if (preg_match('/^backadel/', $fileid)) {
            $backadel_path = get_config('block_backadel', 'path');

            $copy_cmd = function ($path) use ($CFG, $backadel_path, $fileid) {
                copy($CFG->dataroot . $backadel_path . $fileid, $path);
            };
        } else {

            $backup = $DB->get_record('files', array('id' => $fileid));

            if(empty($backup))
                throw new Exception(simple_restore_utils::_s('no_file'));

            $fs = get_file_storage();
            $browser = get_file_browser();
            $filecontext= get_context_instance_by_id($backup->contextid);

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
                self::permission(
                    'canrestore',
                    get_context_instance(CONTEXT_COURSE, $courseid)
                ),
                false,
                true
            );

            $copy_cmd = function ($path) use ($fileinfo) {
                $fileinfo->copy_to_pathname($path);
            };
        }

        $filename = restore_controller::get_tempdir_name($courseid, $USER->id);
        $pathname = $CFG->dataroot . '/temp/backup/' . $filename;
        $copy_cmd($pathname);

        return $filename;
    }
}

class simple_restore {
    function __construct($course, $filename, $restore_to = 0) {
        if(empty($course))
            throw new Exception(simple_restore_utils::_s('no_context'));
        if(empty($filename))
            throw new Exception(simple_restore_utils::_s('no_file'));

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
        $_POST['targetid'] = $this->course->id;

        $rtn = restore_ui::engage_independent_stage(
            restore_ui::STAGE_DESTINATION, $this->context->id
        );
        $rtn->process();

        return $rtn;
    }

    private function process_schema($rc) {

        $_POST['stage'] = restore_ui::STAGE_SCHEMA;
        $restore = new restore_ui($rc, array('contextid' => $this->context->id));

        // Forge posts
        $_POST['restore'] = $restore->get_restoreid();

        $tasks = $this->rip_ui($restore)->get_tasks();
        foreach ($tasks as $task) {
            $settings = $task->get_settings();
            foreach($settings as $setting) {
                $setting_name = $setting->get_name();
                if(preg_match('/(.+)_(\d+)_(.+)/', $setting_name, $matches)) {
                    $module = $matches[1];
                    $type = $matches[3];
                    $admin_setting_key = $module.'_'.$type;
                } else {
                    $admin_setting_key = $setting_name;
                }
                $admin_setting = get_config('simple_restore', $admin_setting_key);
                if(!is_numeric($admin_setting)) {
                    continue;
                }
                // Set admin value
                $setting->set_value($admin_setting);
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
        global $USER;

        // Confirmed ... process destination
        $confirmed = $this->process_destination(
            $this->process_confirm()
        );

        // Setting up controller ... tmp tables
        $rc = new restore_controller(
            $confirmed->get_filepath(),
            $confirmed->get_course_id(),
            backup::INTERACTIVE_YES,
            backup::MODE_GENERAL,
            $USER->id,
            $confirmed->get_target()
        );

        // Probably good to do this
        unset($confirmed);
        $this->process_final(
            $this->process_schema($rc)
        );

        return true;
    }
}
