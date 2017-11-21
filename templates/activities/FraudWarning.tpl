<h2>{$title}</h2>
<table>
  <tr>
    <td>{ts}Contact{/ts}</td>
    <td>{$contactName}</td>
  </tr>
  <tr>
    <td>{ts}Recruiter{/ts}</td>
    <td>{$recruiterName}</td>
  </tr>
  <tr>
    <td>{ts}Activity{/ts}</td>
    <td>{$activityType} - {$activitySubject}</td>
  </tr>
  <tr>
    <td>{ts}Warning{/ts}</td>
    <td>{$warningMessage}</td>
  </tr>
</table>
<div class="action-link">
  <a class="button" href="{$viewContactUrl}"><span class="icon ui-icon-info"></span>{ts}View Contact{/ts}</a>
  <a class="button" href="{$viewRecruiterUrl}"><span class="icon ui-icon-info"></span>{ts}View Recruiter{/ts}</a>
  <a class="button" href="{$viewContributionUrl}"><span class="icon ui-icon-info"></span>{ts}View Contribution{/ts}</a>
</div>