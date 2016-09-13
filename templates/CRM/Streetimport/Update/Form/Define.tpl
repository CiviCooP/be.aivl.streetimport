<div class="crm-block crm-form-block">
  <p>Using batch <b>'{$importBatchId}'</b>.</p>

  <p>Updating {$contacts|@count} contacts and associated entities.</p>
  <ul>
    {foreach from=$contacts item=contact}
    <li><a href='{crmURL p="civicrm/contact/view" q="id=reset=1&`$contact.id`"}'>{$contact.display_name}</a></li>
    {/foreach}
  </ul>
  <p>Values found in import file:</p>
  <table>
    <tr>
      <td>Campaign ID</td>
      <td></td>
    </tr>
    <tr>
      <td>Recruiting organization</td>
      <td></td>
    </tr>
  </table>

  {foreach from=$elementNames item=elementName} {$elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
  {/foreach}

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
