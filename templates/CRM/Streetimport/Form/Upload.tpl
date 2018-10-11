{*-------------------------------------------------------+
| StreetImporter                                         |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*}


<div id="help" class="description">
    {ts domain='be.aivl.streetimport'}Files uploaded here will immediately imported via the StreetImport framework.{/ts}
    {ts domain='be.aivl.streetimport'}Please make sure that they are not too big, otherwise they might hit a timeout half way through the import. Larger files should be processed by a scheduled job.{/ts}
</div>

<div class="crm-section">
  <div class="label">{$form.import_files.label}</div>
  <div class="content">{$form.import_files.html}</div>
  <div class="clear"></div>
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
  <script type="application/javascript">
      cj(document).ready(function() {
          cj("#import_files").closest("form").attr('enctype' ,'multipart/form-data');
          cj("#import_files").attr('name', 'import_files[]');
      });
  </script>
{/literal}
