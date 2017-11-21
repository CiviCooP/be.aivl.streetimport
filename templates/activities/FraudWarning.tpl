<h2>{ts}Possible Fraud Warning Details{/ts}</h2>
<table>
  <tr>
    <td>{ts}Warning :{/ts}</td>
    <td>{$warning_message}</td>
  </tr>
  {if !empty($recruiter_id)}
    <tr>
      <td>{ts}Recruiter{/ts}</td>
      <td>{$recruiter_name}</td>
      <td><a class="view-contact no-popup" href="{$recruiter_url}">{ts}View Recruiter{/ts}</a><td>
    </tr>
  {/if}
  {if !empty($other_contacts)}
    {foreach from=$other_contacts key=other_contact_id item=other_contact_name}
      <tr>
        <td>{ts}Other contact using this IBAN{/ts}</td>
        <td>{$other_contact_name}</td>
        <td><a class="view-contact no-popup" href="{$other_contact_urls.$other_contact_id}">{ts}View Contact{/ts}</a><td>
      </tr>
    {/foreach}
  {/if}
</table>
<div class="action-link">
  <a class="button" href="{$mandate_url}"><span class="icon ui-icon-info"></span>{ts}View Mandate{/ts}</a>
</div>
