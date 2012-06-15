/**
 * Created with JetBrains PhpStorm.
 * User: jonathan
 * Date: 5/25/12
 * Time: 4:07 PM
 * To change this template use File | Settings | File Templates.
 */
jQuery(document).ready(function($){
    $('#addContact').on('click', function(e){
        e.preventDefault();
        var contactInput = $('#firstContactAdded').clone();
        var $el = $(e.currentTarget);
        $el.before(contactInput);
    });

    $('select[name=action2]').change(function(e){
        $('select[name=action]').val($(this).val());
    })


});