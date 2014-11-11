<?php

function xmldb_workshop_install() {
    global $DB;

    $DB->set_field('modules', 'visible', 0, array('name'=>'workshop'));
}

