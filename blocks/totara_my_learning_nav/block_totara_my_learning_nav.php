<?php //$Id$

class block_totara_my_learning_nav extends block_base {

    function init() {
        $this->title = get_string('title', 'block_totara_my_learning_nav');
        $this->version = 2009120100;
    }

    function instance_allow_multiple() {
        return true;
    }

    function specialization() {
        $this->title = get_string('displaytitle', 'block_totara_my_learning_nav');
    }

    function get_content() {
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $renderer = $this->page->get_renderer('block_totara_my_learning_nav');
        $this->content->text = $renderer->my_learning_nav();
        $this->content->footer = '';

        return $this->content;
    }

}
?>
