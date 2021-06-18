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
      <table>

        <thead>
          <tr>
            <th>{ts domain="be.aivl.streetimport"}File{/ts}</th>
            <th>{ts domain="be.aivl.streetimport"}Size{/ts}</th>
            <th>{ts domain="be.aivl.streetimport"}Last Changed{/ts}</th>
          </tr>
        </thead>

        <tbody>
        {if $locations.$current.count > 0}
            {foreach from=$locations.$current.files item='file'}
              <tr>

                <td>
                  <a href="{$file.url}">
                    <i class="crm-i {$file.icon}" aria-hidden="true"></i>
                      {$file.name}
                  </a>
                </td>

                <td>{$file.size}</td>

                <td>{$file.date}</td>

              </tr>
            {/foreach}
        {else}
          <tr>
            <td colspan="3">{ts domain="be.aivl.streetimport"}No files{/ts}</td>
          </tr>
        {/if}
        </tbody>

      </table>
    </div>

    {if $type == $current}
      {* TODO: Add button (link) to Upload form on "Import" tab. *}
    {/if}

  </div>
</div>
