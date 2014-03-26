/*
 * Add event handlers to all text field and textareas with a placeholder attribute set
 */
$(document).ready(function() {
    $('input[placeholder], textarea[placeholder]').placeholder();
});
