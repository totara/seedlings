var url = $('input[name$=eventhandlers]').val();

$.ajax({
    url: url,
    dataType: "script",
    success: function( data, textStatus, jqxhr ) {
        YUI().use('moodle-mod_facetoface-signupform', function (Y) {
            M.mod_facetoface.signupform.init();
        });
    },
    cache: true
});
