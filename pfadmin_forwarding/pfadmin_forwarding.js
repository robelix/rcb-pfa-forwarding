/* Forwarding interface (tab) */

window.rcmail && rcmail.addEventListener('init', function(evt) {

    rcmail.register_command('plugin.pfadmin_forwarding', function() { rcmail.goto_url('plugin.pfadmin_forwarding') }, true);

    rcmail.register_command('plugin.pfadmin_forwarding-save', function() {
        var input_address = $("[name='_forwardingaddress']");
        var input_enabled = rcube_find_object('_forwardingenabled');
        document.forms.forwardingform.submit();
    }, true);
});
