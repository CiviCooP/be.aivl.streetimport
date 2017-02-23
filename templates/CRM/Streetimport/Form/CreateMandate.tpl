<h3>{ts}Please add to and correct the fields below and click 'create' to create a new mandate. This will also record a new bank account when necessary.{/ts}</h3>
<div class="crm-block crm-form-block">
<p>{ts 1=$activityType 2=$activityId}This form has been prefilled with data from the %1 activity %2.{/ts}</p>
  {* HEADER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  <table class="form-layout">
    <tr>
      <td class="label">{$form.amount.label}</td>
      <td class="view-value">{$form.amount.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.reference.label}</td>
      <td class="view-value">{$form.reference.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.frequency_interval.label}</td>
      <td class="view-value">{$form.frequency_interval.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.iban.label}</td>
      <td class="view-value">{$form.iban.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.bic.label}</td>
      <td class="view-value">{$form.bic.html}</td>
    </tr>
    <tr>
      <td class="label">{$form.bank_name.label}</td>
      <td class="view-value">{$form.bank_name.html}</td>
    </tr>
  </table>

  {* FOOTER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
