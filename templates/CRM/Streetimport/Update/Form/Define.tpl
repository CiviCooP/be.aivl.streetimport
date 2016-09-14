<div class="crm-block crm-form-block">
  <p>Updating '<b>{$batchId}.csv</b>'</p>
  <h2>Batch includes the following {$contacts|@count} contacts:</h2>
  <ul>
    {foreach from=$contacts item=contact}
    <li><a href='{crmURL p="civicrm/contact/view" q="cid=`$contact.id`&reset=1"}'>{$contact.display_name}</a></li>
    {/foreach}
  </ul>
  <h2>Values found in import file:</h2>
  <table class="form-layout">
    <tr>
      <td class="label">Campaign</td>
      <td class="view-value">
        {foreach from=$old.campaigns item=campaign name=campaigns}
          <a href='{crmURL p="civicrm/campaign/add" q="reset=1&action=update&id=`$campaign.id`"}'>{$campaign.title}</a>{if !$smarty.foreach.campaigns.last}, {/if}
        {/foreach}
      </td>
    </tr>
    <tr>
      <td class="label">Recruiting organization</td>
      <td class="view-value">
        {foreach from=$old.recruitingOrganizations item=contact name=recruitingOrganizations}
          <a href='{crmURL p="civicrm/contact/view" q="id=reset=1&`$contact.id`"}'>{$contact.display_name}</a>{if !$smarty.foreach.recruitingOrganizations.last}, {/if}
        {/foreach}

      </td>
    </tr>
  </table>
  <h2>Update these values to:</h2>
  <table class="form-layout">
    <tr>
      <td class="label">{$form.campaign_id.label}</td>
      <td class="view-value">{$form.campaign_id.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.recruiting_organization_id.label}</td>
      <td class="view-value">{$form.recruiting_organization_id.html}</td>
      </td>
    </tr>
  </table>



  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
