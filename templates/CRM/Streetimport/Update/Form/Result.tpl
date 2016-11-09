<div class="crm-block crm-form-block">
  <p>Updating '<b>{$batchId}.csv</b>'</p>
  <h2>The following {$contacts|@count} contacts have been updated:</h2>
  <ul>
    {foreach from=$contacts item=contact}
    <li><a href='{crmURL p="civicrm/contact/view" q="cid=`$contact.id`&reset=1"}'>{$contact.display_name}</a></li>
    {/foreach}
  </ul>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
