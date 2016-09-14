<div class="crm-block crm-form-block">
  <p>Updating '<b>{$batchId}.csv</b>'</p>
  <h2>Batch includes the following {$contacts|@count} contacts:</h2>
  <ul>
    {foreach from=$contacts item=contact}
    <li><a href='{crmURL p="civicrm/contact/view" q="cid=`$contact.id`&reset=1"}'>{$contact.display_name}</a></li>
    {/foreach}
  </ul>
  <h2>Import will proceed as follows:</h2>
  <table>
    <tr>
      <th>Field</th>
      <th>Old value</th>
      <th>New value</th>
    </tr>
    <tr>
      <td>Campaign</td>
      <td>
        {foreach from=$old.campaigns item=campaign name=campaigns}
        <a href='{crmURL p="civicrm/campaign/add" q="reset=1&action=update&id=`$campaign.id`"}'>{$campaign.title}</a>{if !$smarty.foreach.campaigns.last}, {/if} {/foreach}
      </td>
      <td><a href='{crmURL p="civicrm/campaign/add" q="reset=1&action=update&id=`$new.campaign.id`"}'>{$new.campaign.title}</a></td>
    </tr>
    <tr>
      <td>Recruiting organisation</td>
      <td>
        {foreach from=$old.recruitingOrganizations item=contact name=recruitingOrganizations}
        <a href='{crmURL p="civicrm/contact/view" q="id=reset=1&`$contact.id`"}'>{$contact.display_name}</a>{if !$smarty.foreach.recruitingOrganizations.last}, {/if} {/foreach}
      </td>
      <td><a href='{crmURL p="civicrm/contact/view" q="id=reset=1&`$new.recruitingOrganization.id`"}'>{$new.recruitingOrganization.display_name}</a></td>
    </tr>
  </table>
  <p>Please confirm the details above and click 'update' to run the update.</p>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
