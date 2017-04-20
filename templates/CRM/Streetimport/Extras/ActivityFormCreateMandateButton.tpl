
{literal}
<script type="text/javascript">
  cj(function ($) {
  $('.crm-submit-buttons').prepend(
    '{/literal}<a href="{crmURL p="civicrm/streetimport/createmandate" q="contact_id=`$contactId`&activity_id=`$activityId`&activity_type_id=`$atype`&context=activity&searchContext=activity"}" class="add button" title="Create mandate"><span><i class="crm-i fa-plus"></i> Create mandate</span></a>{literal}');
});
</script>
{/literal}
