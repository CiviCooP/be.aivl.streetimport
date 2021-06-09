{*-------------------------------------------------------------+
| StreetImporter Record Handlers                               |
| Copyright (C) 2017 SYSTOPIA / CiviCooP                       |
| Author: Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>    |
|         B. Endres (SYSTOPIA) <endres@systopia.de>            |
|         J. Schuppe (SYSTOPIA) <schuppe@systopia.de>          |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*}
<div class="crm-block crm-content-block">
  <div class="ui-tabs">

    <ul class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header">
        {foreach from=$locations key='type' item='location'}
          <li class="ui-tabs-tab ui-corner-top ui-state-default ui-tab {if $type == $current}ui-tabs-active ui-state-active{/if}">
            <a class="ui-tabs-anchor" href="{crmURL q="location=$type"}">
                {$location.title}
                <span class="badge badge-light">{$location.count}</span>
            </a>
          </li>
        {/foreach}
    </ul>

    <div class="ui-tabs-panel ui-widget-content ui-corner-bottom">
      {if $locations.$current.count > 0}
          <table>
            {foreach from=$locations.$current.files item='file'}
                <tr>
                  <td>
                    <a href="{$file.url}">
                      <i class="crm-i {$file.icon}" aria-hidden="true"></i>
                      {$file.name}
                    </a>
                  </td>
                </tr>
            {/foreach}
          </table>
      {else}
          {ts domain="be.aivl.streetimport"}No files{/ts}
      {/if}
    </div>

  </div>
</div>
