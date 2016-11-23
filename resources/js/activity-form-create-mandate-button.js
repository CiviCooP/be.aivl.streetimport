cj(function ($) {
  $('.crm-submit-buttons').prepend(
    '<a href="/civicrm/streetimport/createmandate' +
    '?contact_id=' + streetImportGetURLParameter('cid') +
    '&activity_id=' + streetImportGetURLParameter('id') +
    '&activity_type_id=' + streetImportGetURLParameter('atype') +
    '" class="add button" title="Create mandate"><span><i class="crm-i fa-plus"></i> Create mandate</span></a>');
});


function streetImportGetURLParameter(sParam){
    var sPageURL = window.location.search.substring(1);
    var sURLVariables = sPageURL.split('&');
    for (var i = 0; i < sURLVariables.length; i++)
    {
        var sParameterName = sURLVariables[i].split('=');
        if (sParameterName[0] == sParam)
        {
            return sParameterName[1];
        }
    }
}
