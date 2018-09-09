$(document).ready(function() {

// The form may have more than 1000 fields, so they are jsonified before submit.
$('#content form').append('<input name="fieldsets" id="fieldsets" value="[]" type="hidden">');
$('#content form').submit(function(event) {
    event.preventDefault();
    var data = $('#content form').serializeArray();
    var fieldsets = {};
    $.each(data, $.proxy(function(index, element) {
        if (!element) {
            return;
        }
        var posChar = element.name.indexOf('[');
        if (posChar <= 0) {
            return;
        }
        var name = element.name.slice(0, posChar);
        if (fieldsets[name] === undefined) {
            fieldsets[name] = [];
        }
        fieldsets[name].push(element);
        $('input[name="' + element.name + '"]').remove();
    }, this));

    $('#fieldsets').val(JSON.stringify(fieldsets));
    $(this).unbind('submit').submit();
});

});
