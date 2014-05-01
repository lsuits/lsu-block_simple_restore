<?php

require_once $CFG->libdir . '/formslib.php';

class list_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $m->addElement('text', 'shortname', get_string('shortname'));
        $m->setType('shortname',PARAM_TEXT);

        $m->addElement('hidden', 'id');
        $m->setType('id',PARAM_INT);
        
        $m->addElement('hidden', 'restore_to');
        $m->setType('restore_to',PARAM_INT);

        $buttons = array(
            $m->createElement('submit', 'submit', get_string('search')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}
