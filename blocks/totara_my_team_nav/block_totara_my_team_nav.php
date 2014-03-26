<?php //$Id$

class block_totara_my_team_nav extends block_base {

    function init() {
        $this->title = get_string('title', 'block_totara_my_team_nav');
        $this->version = 2009120100;
    }

    function instance_allow_multiple() {
        return true;
    }

    function specialization() {
        $this->title = get_string('displaytitle', 'block_totara_my_team_nav');
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = totara_print_my_team_nav(true);
        $this->content->footer = '';

        return $this->content;
    }

}
?>
