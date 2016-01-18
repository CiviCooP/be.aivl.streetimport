<div class="crm-content-block crm-block">
  <div id="help">
    The existing load types are listed below. You can add, edit the settings or delete them from this screen.
  </div>
  <div class="action-link">
    <a class="button new-option" href="{$addUrl}">
      <span><div class="icon add-icon"></div>{ts}Add Load Type{/ts}</span>
    </a>
  </div>
  {include file="CRM/common/pager.tpl" location="top"}
  {include file='CRM/common/jsortable.tpl'}
  <div id="laod_type-wrapper" class="dataTables_wrapper">
    <table id="load_type-table" class="display">
      <thead>
      <tr>
        <th>{ts}Load Type ID{/ts}</th>
        <th>{ts}Load Type{/ts}</th>
        <th id="nosort"></th>
      </tr>
      </thead>
      <tbody>
      {assign var="rowClass" value="odd-row"}
      {assign var="rowCount" value=0}
      {foreach from=$loadTypes key=loadTypeId item=loadType}
        {assign var="rowCount" value=$rowCount+1}
        <tr id="row{$rowCount}" class={$rowClass}>
          <td>{$loadTypeId}</td>
          <td>{$loadType.label}</td>
          <td>
              <span>
                {foreach from=$loadType.actions item=actionLink}
                  {$actionLink}
                {/foreach}
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
      <span><div class="icon add-icon"></div>{ts}Add Load Type{/ts}</span>
    </a>
  </div>
</div>