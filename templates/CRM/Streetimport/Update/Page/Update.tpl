<p>Select a batch to update from the list of processed batches below:</p>

{foreach from=$imports item=import}
<p><a href='{crmURL p="civicrm/streetimport/update/import" q="id=`$import.id`"}'>{$import.id}</a></p>
{/foreach} civicrm/contact/view?reset=1&cid=11
