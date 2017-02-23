<div class="crm-block crm-form-block">

  {* HEADER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  {foreach from=$elementNames item=elementName}
    <div class="crm-section">
      <div class="label">{$form.$elementName.label}</div>
      <div class="content">{$form.$elementName.html}</div>
      {* show prefix rules after male_gender_id *}
      {if $elementName == "unknown_gender_id"}
        <hr />
        <h3>Prefix Rules</h3>
        <div id="help">
          {ts}You can set the prefix rules in this section.{/ts}
        </div>
        <div class="action-link">
          <a class="button new-option" href="{$addUrl}">
            <span><div class="icon add-icon"></div>{ts}Add Prefix Rule{/ts}</span>
          </a>
        </div>
        <div id="prefix_rule-wrapper" class="dataTables_wrapper">
          <table id="prefix_rule-table" class="display">
            <thead>
            <tr>
              <th>{ts}Gender{/ts}</th>
              <th>{ts}Prefix from Import file{/ts}</th>
              <th>{ts}Prefix in CiviCRM{/ts}</th>
              <th id="nosort"></th>
            </tr>
            </thead>
            <tbody>
            {assign var="rowClass" value="odd-row"}
            {assign var="rowCount" value=0}
            {foreach from=$prefixRules key=prefixRuleId item=prefixRule}
              {assign var="rowCount" value=$rowCount+1}
              <tr id="row{$rowCount}{$prefixRuleId}" class={$rowClass}>
                <td hidden="1">{$prefixRuleId}</td>
                <td>{$prefixRule.gender}</td>
                <td>{$prefixRule.import_prefix}</td>
                <td>{$prefixRule.civicrm_prefix}</td>
                <td>
                  <span>
                    {$prefixRule.actionLink}
                  </span>
                </td>
              </tr>
              {if $rowClass eq "odd-row"}
                {assign var="rowClass" value="even-row"}
              {else}
                {assign var="rowClass" value="odd-row"}
              {/if}
            {/foreach}
            </tbody>
          </table>
        </div>
        {include file="CRM/common/pager.tpl" location="bottom"}
        <div class="action-link">
          <a class="button new-option" href="{$addUrl}">
            <span><div class="icon add-icon"></div>{ts}Add Prefix Rule{/ts}</span>
          </a>
        </div>
        <hr />
      {/if}
      <div class="clear"></div>
    </div>

  {/foreach}

  {* FOOTER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
