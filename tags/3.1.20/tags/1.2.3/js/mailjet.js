/**
 * Created with JetBrains PhpStorm.
 * User: jonathan
 * Date: 5/25/12
 * Time: 4:07 PM
 * To change this template use File | Settings | File Templates.
 */
jQuery(document).ready(function($){
    showPorts = function($el){
        if($el.attr('checked') == 'checked'){
            $('#mailjet_port').find('option').show();
            $('#mailjet_port').find('option[value=25]').hide().removeAttr('selected');
            $('#mailjet_port').find('option[value=587]').hide().removeAttr('selected');
            $('#mailjet_port').find('option[value=588]').hide().removeAttr('selected');
            $('#mailjet_port').find('option[value=80]').hide().removeAttr('selected');
            $('#mailjet_port').find('option[value=465]').attr('selected', 'selected');

        }else{
            $('#mailjet_port').find('option').show();
            $('#mailjet_port').find('option[value=465]').hide().removeAttr('selected');
            $('#mailjet_port').find('option[value=25]').attr('selected', 'selected');
        }
    }
    $('#addContact').on('click', function(e){
        e.preventDefault();
        var contactInput = $('#firstContactAdded').clone();
        var $el = $(e.currentTarget);
        $el.before(contactInput);
    });

    $('select[name=action2]').change(function(e){
        $('select[name=action]').val($(this).val());
    })

    $('#mailjet_ssl').change(function(e){
        showPorts($(this));
    })


    showPorts($('#mailjet_ssl'));
});