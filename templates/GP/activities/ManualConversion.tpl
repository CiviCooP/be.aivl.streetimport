<h2>Suggested conversion: '{$contact.contact_type}' to 'Individual'</h2>
<p>The changes below couldn't be applied because the contact is not an individual.</p>
<table>
  <thead>
    <tr>
      <td><b>Attribute<b></td>
      <td><b>Submitted Value</b></td>
    </tr>
  </thead>
  <tbody>
    {foreach from=$update key=attribute item=value}
      <tr>
        <td>
          {if $attribute eq 'first_name'}
            First Name
          {elseif $attribute eq 'last_name'}
            Last Name
          {elseif $attribute eq 'current_employer'}
            Current Employer
          {elseif $attribute eq 'prefix_id'}
            Prefix
          {elseif $attribute eq 'birth_date'}
            Birth Date
          {elseif $attribute eq 'formal_title'}
            Formal Title
          {else}
            Birth Year
          {/if}
        </td>
        <td>
          {$value}
        </td>
      </tr>
    {/foreach}
  </tbody>
</table>