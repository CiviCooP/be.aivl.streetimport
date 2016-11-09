<p>Select a batch to update from the list of processed batches below:</p>

{foreach from=$imports item=import}
<p>
  <a href='{crmURL p="civicrm/streetimport/update/run" q="id=`$import.id`"}'>{$import.id}</a>
  <!-- <a href='{crmURL p="civicrm/streetimport/update/reset" q="id=`$import.id`"}'>(reset)</a> -->
</p>
{/foreach}
