{if !empty($company_name)}
  {ts domain="be.aivl.streetimport"}mandate belongs to company:{/ts}&nbsp{$company_name}
  <a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$company_id"}" class="button no-popup" target="_blank">{ts domain="be.aivl.streetimport"}Click to View {/ts}{$company_name}</a>
{/if}