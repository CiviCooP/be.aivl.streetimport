<h2>{$title}</h2>
<table>
  <thead>
    <tr>
      <td></td>
      <td>New Address</td>
      {if $old_address}
      <td>Old Address</td>
      {/if}
    </tr>
  </thead>
  <tbody>
    {foreach from=$fields item=field}
      {if $address.$field or $old_address.$field}
      <tr>
        <td>{$field}</td>
        <td>{$address.$field}</td>
        {if $old_address}
        <td>{$old_address.$field}</td>
        {/if}
      </tr>
      {/if}
    {/foreach}
  </tbody>
</table>