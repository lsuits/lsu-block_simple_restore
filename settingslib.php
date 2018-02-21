<?php

class semester_backup_path_setting extends admin_setting_configtext {
    public function validate($data) {
        global $CFG;

        // Must validate through original validation
        $is_validated = parent::validate($data);

        if (is_string($is_validated)) {
            return $is_validated;
        }

        $chars = str_split($data);
        if (current($chars) != '/' and end($chars) != '/') {
            return get_string('config_path_surround', 'block_simple_restore');
        }

        $real_path = "$CFG->dataroot$data";

        if (!file_exists($real_path)) {
            return get_string('config_path_not_exists', 'block_simple_restore');
        }

        if (!is_writable($real_path)) {
            return get_string('config_path_not_writable', 'block_simple_restore');
        }

        return true;
    }
}
