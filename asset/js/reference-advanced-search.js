$(document).ready(function() {

    // @see application/asset/js/global.js.

    // Add a value.
    $('form').on('o:value-created', function(e) {
        // Function check for the property and type and display the third values dropdow accordingly.
        var queryRow = $(this).find('#property-queries .inputs .value').last();
        initQueryRow(queryRow);
    });

    /**
     * Initialize a query row.
     */
    function initQueryRow(queryRow) {
        evalulatePropertyType(queryRow);

        var queryProperty = queryRow.find('.query-property');
        queryProperty.change(function(e) {
            evalulatePropertyType($(this).parent());
        });

        var queryType = queryRow.find('.query-type');
        queryType.change(function(e) {
            evalulatePropertyType($(this).parent());
        });
    }

    /**
     * Evaluate property and type to fill value with a dropdown or the default field.
     */
    function evalulatePropertyType(queryRow) {
        var property = queryRow.find('.query-property').find(':selected').data('term');
        var type = queryRow.find('.query-type').find(':selected').val();
        if ((property !== undefined && property !== '') && (type !== undefined && type !== '')) {
            if (type === 'eq' || type === 'neq') {
                // Getting properties values via reference json output.
                var url = basePath + '/s/' + siteSlug + '/reference/' + property + '?output=json';
                var isValuesDropdown = false;
                var propertiesValues = {};
                $.getJSON(url)
                .done(function(data) {
                    isValuesDropdown = true;
                    propertiesValues = data;
                })
                .fail(function() {
                    propertiesValues = {};
                })
                .always(function() {
                    if (isValuesDropdown) {
                        populateValuesList(queryRow, propertiesValues);
                    } else {
                        displayValueField(queryRow);
                    }
                });
            } else if (type !== 'ex' && type !== 'nex') {
                // In all other cases, it is a value to display.
                displayValueField(queryRow);
            }
        }
    }

    /**
     * Display the default Omeka field, if any.
     */
    function displayValueField(queryRow) {
         var valueIndex = queryRow.find('.query-property').prop('name').substr(9);
         valueIndex = valueIndex.substr(0, valueIndex.indexOf(']'));
         var valueField = queryRow.find('.query-text');
         var valuesList = queryRow.find('.query-select');
         var removeBtn = queryRow.find('button');
         // var valueText = valueField ? valueField.val() : '';
         var valueText = '';
         if (valueField.length) {
             valueText = valueField.val();
             valueField.remove();
         }
         if (valuesList.length) {
             valueText = valuesList.val();
             valuesList.remove();
             queryRow.find('.chosen-container').remove();
         }
         valueField = '<input type="text" class="query-text" name="property[' + valueIndex + '][text]" value="' + valueText + '" aria-label="' + 'Query text' + '">';
         $(valueField).insertBefore(removeBtn);
    }

    /**
     * Display the dropdown list for the property, if available.
     */
    function populateValuesList(queryRow, propertiesValues) {
        var valueIndex = queryRow.find('.query-property').prop('name').substr(9);
        valueIndex = valueIndex.substr(0, valueIndex.indexOf(']'));
        var valueField = queryRow.find('.query-text');
        var valuesList = queryRow.find('.query-select');
        var removeBtn = queryRow.find('button');
        var valueText = '';
        if (valueField.length) {
            valueText = valueField.val();
            valueField.remove();
        }
        if (valuesList.length) {
            valueText = valuesList.val();
            valuesList.remove();
            queryRow.find('.chosen-container').remove();
        }
        var valuesList = '<select class="query-select chosen-select" data-placeholder="' + 'Choose a value' + '" name="property[' + valueIndex + '][text]" aria-label="' + 'Query select' + '">';
        valuesList += '<option value=""></option>';
        for (var propertyVal in propertiesValues) {
            var selected = (propertyVal === valueText) ? ' selected="selected"' : '';
            valuesList += '<option value="' + propertyVal + '"' + selected + '>' + propertyVal + ' (' + propertiesValues[propertyVal] + ')</option>';
        }
        valuesList += '<select>';
        $(valuesList).insertBefore(removeBtn);
        queryRow.find('.chosen-select').chosen({});
    }

    function init() {
        // Init is done with the query as text.
        $('form #property-queries .inputs .value').each(function () {
            var queryRow = $(this);
            initQueryRow(queryRow);
        });
    }

    init();

});
